<?php
namespace App\Managers;
use App\Interfaces\ManagersInterface;
use App\Core\Session;

/**
 * Class UsersManager
 * @package App\Managers
 */
class UsersManager implements ManagersInterface
{
    private $db;

    /**
     * UsersManager constructor.
     *
     * @param \PDO $database
     */
    public function __construct(\PDO $database)
    {
        $this->db = $database;
    }

    /**
     * @param string $email
     *
     * @return bool
     */
    public function checkUserExist(string $email)
    {
        $user = $this->db->query('SELECT id_user FROM b_users WHERE email = ?', [$email])->fetch();
        if (!$user) {
            return true;
        }
        return false;
    }

    /**
     * Checks the validity of an account validation token.
     *
     * @param int    $id_user
     * @param string $token
     *
     * @return mixed
     */
    public function checkValidAccount(int $id_user, string $token)
    {
        return $this->db->query('SELECT email
                                          FROM b_users
                                          WHERE id_user = ? AND create_token IS NOT NULL AND create_token = ? AND date_create_token > DATE_SUB(NOW(), INTERVAL 30 MINUTE)', [$id_user, $token])->fetch();
    }

    /**
     * Validates an account.
     *
     * @param int $id_user
     */
    public function validAccount(int $id_user)
    {
        $this->db->query('UPDATE b_users SET id_group = 3, create_token = NULL, date_create_token = NULL, date_upd = NOW() WHERE id_user = ?', [$id_user]);
    }

    /**
     * Creates a token for creating an account.
     *
     * @param string $lastname
     * @param string $firstname
     * @param string $email
     * @param string $password
     */
    public function create(string $lastname, string $firstname, string $email, string $password)
    {
        $create_token = $this->token(60);
        $password = $this->hashPassword($password);
        $params = [
            'id_group' => 4,
            'lastname' => $lastname,
            'firstname' => $firstname,
            'email' => $email,
            'password' => $password,
            'create_token' => $create_token
        ];
        $this->db->query('INSERT INTO b_users (id_group, lastname, firstname, email, password, create_token, date_create_token)
                                   VALUES (:id_group, :lastname, :firstname, :email, :password, :create_token, NOW())', $params);
        $id_user = $this->db->lastInsertId();
        $method = __FUNCTION__.'User';
        Session::getInstance()->read('Mail')->$method($email, $id_user, $create_token);
    }

    /**
     * Creates a token for resetting a password.
     *
     * @param string $email
     *
     * @return bool
     */
    public function resetToken(string $email)
    {
        $user = $this->db->query('SELECT id_user FROM b_users WHERE email = ?', [$email])->fetch();
        if ($user) {
            $reset_token = $this->token(60);
            $this->db->query('UPDATE b_users SET reset_token = ?, date_reset_token = NOW() WHERE id_user = ?', [$reset_token, $user->id_user]);
            $method = __FUNCTION__;
            Session::getInstance()->read('Mail')->$method($email, $user->id_user, $reset_token);
            return true;
        }
        return false;
    }

    /**
     * Checks the validity of a password token.
     *
     * @param int    $id_user
     * @param string $token
     *
     * @return mixed
     */
    public function checkResetPassword(int $id_user, string $token)
    {
        return $this->db->query('SELECT email
                                          FROM b_users
                                          WHERE id_user = ? AND reset_token IS NOT NULL AND reset_token = ? AND date_reset_token > DATE_SUB(NOW(), INTERVAL 30 MINUTE)', [$id_user, $token])->fetch();
    }

    /**
     * Hash a password.
     *
     * @param string $password
     *
     * @return false|string|null
     */
    public function hashPassword(string $password)
    {
        return password_hash($password, PASSWORD_BCRYPT);
    }

    /**
     * Save the new password.
     *
     * @param string $new_password
     * @param int    $id_user
     * @param bool   $reset
     */
    public function newPassword(string $new_password, int $id_user, $reset = false)
    {
        $password = $this->hashPassword($new_password);
        if ($reset) {
            $this->db->query('UPDATE b_users SET password = ?, reset_token = NULL, date_reset_token = NULL, date_upd = NOW() WHERE id_user = ?', [$password, $id_user]);
        } else {
            $this->db->query('UPDATE b_users SET password = ?, date_upd = NOW() WHERE id_user = ?', [$password, $id_user]);
        }
    }

    /**
     * Create a token.
     *
     * @param int $length
     *
     * @return false|string
     */
    private function token(int $length)
    {
        $alphabet = '0123456789azertyuiopqsdfghjklmwxcvbnAZERTYUIOPQSDFGHJKLMWXCVBN';
        return substr(str_shuffle(str_repeat($alphabet, $length)), 0, $length);
    }
}