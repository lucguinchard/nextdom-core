<?php
/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

/* This file is part of NextDom Software.
 *
 * NextDom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * NextDom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with NextDom. If not, see <http://www.gnu.org/licenses/>.
 */

namespace NextDom\Managers;

use NextDom\Enums\DateFormat;
use NextDom\Enums\LogTarget;
use NextDom\Enums\SQLField;
use NextDom\Enums\UserRight;
use NextDom\Helpers\DBHelper;
use NextDom\Helpers\LogHelper;
use NextDom\Helpers\NetworkHelper;
use NextDom\Helpers\NextDomHelper;
use NextDom\Helpers\Utils;
use NextDom\Managers\Parents\BaseManager;
use NextDom\Managers\Parents\CommonManager;
use NextDom\Model\Entity\User;
use NextDom\Repo\RepoMarket;
use PragmaRX\Google2FA\Google2FA;

define('BAD_LOGIN_BLOCK_DURATION', 5);

/**
 * Class UserManager
 * @package NextDom\Managers
 */
class UserManager extends BaseManager
{
    use CommonManager;

    const DB_CLASS_NAME = '`user`';
    const CLASS_NAME = User::class;

    /**
     * Retourne un object utilisateur (si les information de connection sont valide)
     * @param string $_login nom d'utilisateur
     * @param string $_mdp mot de passe en sha512
     * @return User|bool object user
     * @throws \Exception
     */
    public static function connect($_login, $_mdp)
    {
        $sMdp = (!Utils::isSha512($_mdp)) ? Utils::sha512($_mdp) : $_mdp;
        if (ConfigManager::byKey('ldap:enable') == '1' && function_exists('ldap_connect')) {
            LogHelper::addDebug(LogTarget::CONNECTION, __('Authentification par LDAP'));
            $ad = self::connectToLDAP();
            if ($ad !== false) {
                LogHelper::addDebug(LogTarget::CONNECTION, __('Connection au LDAP OK'));
                $ad = ldap_connect(ConfigManager::byKey('ldap:host'), ConfigManager::byKey('ldap:port'));
                ldap_set_option($ad, LDAP_OPT_PROTOCOL_VERSION, 3);
                ldap_set_option($ad, LDAP_OPT_REFERRALS, 0);
                if (!ldap_bind($ad, 'uid=' . $_login . ',' . ConfigManager::byKey('ldap:basedn'), $_mdp)) {
                    LogHelper::addInfo(LogTarget::CONNECTION, __('Mot de passe erroné (') . $_login . ')');
                    return false;
                }
                LogHelper::addDebug(LogTarget::CONNECTION, __('Bind user OK'));
                $result = ldap_search($ad, ConfigManager::byKey('ldap::usersearch') . '=' . $_login . ',' . ConfigManager::byKey('ldap:basedn'), ConfigManager::byKey('ldap:filter'));
                LogHelper::addInfo(LogTarget::CONNECTION, __('Recherche LDAP (') . $_login . ')');
                if ($result) {
                    $entries = ldap_get_entries($ad, $result);
                    if ($entries['count'] > 0) {
                        $user = self::byLogin($_login);
                        if (is_object($user)) {
                            $user->setPassword($sMdp)
                                ->setOptions('lastConnection', date(DateFormat::FULL));
                            $user->save();
                            return $user;
                        }
                        $user = (new User())
                            ->setLogin($_login)
                            ->setPassword($sMdp)
                            ->setOptions('lastConnection', date(DateFormat::FULL));
                        $user->save();
                        LogHelper::addInfo(LogTarget::CONNECTION, __('Utilisateur créé depuis le LDAP : ') . $_login);
                        NextDomHelper::event('user_connect');
                        LogHelper::addInfo('event', __('Connexion de l\'utilisateur ') . $_login);
                        return $user;
                    } else {
                        $user = self::byLogin($_login);
                        if (is_object($user)) {
                            $user->remove();
                        }
                        LogHelper::addInfo(LogTarget::CONNECTION, __('Utilisateur non autorisé à accéder à NextDom (') . $_login . ')');
                        return false;
                    }
                } else {
                    $user = self::byLogin($_login);
                    if (is_object($user)) {
                        $user->remove();
                    }
                    LogHelper::addInfo(LogTarget::CONNECTION, __('Utilisateur non autorisé à accéder à NextDom (') . $_login . ')');
                    return false;
                }
            } else {
                LogHelper::addInfo(LogTarget::CONNECTION, __('Impossible de se connecter au LDAP'));
            }
        }
        $user = self::byLoginAndPassword($_login, $sMdp);
        if (!is_object($user)) {
            $user = self::byLoginAndPassword($_login, sha1($_mdp));
            if (is_object($user)) {
                $user->setPassword($sMdp);
            }
        }
        if (is_object($user)) {
            $user->setOptions('lastConnection', date(DateFormat::FULL));
            $user->save();
            NextDomHelper::event('user_connect');
            LogHelper::addInfo('event', __('Connexion de l\'utilisateur ') . $_login);
        }
        return $user;
    }

    /**
     * @return bool|resource
     * @throws \Exception
     */
    public static function connectToLDAP()
    {
        $ad = ldap_connect(ConfigManager::byKey('ldap:host'), ConfigManager::byKey('ldap:port'));
        ldap_set_option($ad, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($ad, LDAP_OPT_REFERRALS, 0);
        if (ldap_bind($ad, ConfigManager::byKey('ldap:username'), ConfigManager::byKey('ldap:password'))) {
            return $ad;
        }
        return false;
    }

    /**
     * @param $_login
     * @return User|null
     * @throws \NextDom\Exceptions\CoreException
     * @throws \ReflectionException
     */
    public static function byLogin($_login)
    {
        return static::getOneByClauses([SQLField::LOGIN => $_login]);
    }

    /**
     * @param $_login
     * @param $_password
     * @return array|mixed|null
     * @throws \NextDom\Exceptions\CoreException
     * @throws \ReflectionException
     */
    public static function byLoginAndPassword($_login, $_password)
    {
        return static::getOneByClauses([SQLField::LOGIN => $_login, 'password' => $_password]);
    }

    /**
     * @param $_hash
     * @return User
     * @throws \Exception
     */
    public static function byHash($_hash)
    {
        return static::getOneByClauses(['hash' => $_hash]);
    }

    /**
     * @param $_login
     * @param $_hash
     * @return array|mixed|null
     * @throws \NextDom\Exceptions\CoreException
     * @throws \ReflectionException
     */
    public static function byLoginAndHash($_login, $_hash)
    {
        return static::getOneByClauses([SQLField::LOGIN => $_login, 'hash' => $_hash]);
    }

    /**
     *
     * @return User[] Array with all users
     * @throws \Exception
     */
    public static function all()
    {
        return static::getAll();
    }

    /**
     * @param $_rights
     * @return array|mixed|null
     * @throws \NextDom\Exceptions\CoreException
     * @throws \ReflectionException
     */
    public static function searchByRight($_rights)
    {
        $values = [
            'rights' => '%"' . $_rights . '":1%',
            'rights2' => '%"' . $_rights . '":"1"%',
        ];
        $sql = static::getBaseSQL() . '
                WHERE `rights` LIKE :rights
                OR `rights` LIKE :rights2';
        return DBHelper::getAllObjects($sql, $values, self::CLASS_NAME);
    }

    /**
     * @param $_profils
     * @param bool $_enable
     * @return User[]|null
     * @throws \Exception
     */
    public static function byProfils($_profils, $_enable = false)
    {
        return static::getMultipleByClauses(['profils' => $_profils, 'enable' => 1]);
    }

    /**
     * @param $_enable
     * @return array|mixed|null
     * @throws \NextDom\Exceptions\CoreException
     * @throws \ReflectionException
     */
    public static function byEnable($_enable)
    {
        return static::getMultipleByClauses(['enable' => $_enable]);
    }

    public static function failedLogin()
    {
        @session_start();
        $_SESSION['failed_count'] = (isset($_SESSION['failed_count'])) ? $_SESSION['failed_count'] + 1 : 1;
        $_SESSION['failed_datetime'] = strtotime('now');
        @session_write_close();
        // Wait 5 seconds (brute force protection)
        sleep(BAD_LOGIN_BLOCK_DURATION);
    }

    public static function removeBanIp()
    {
        $cache = CacheManager::byKey('security::banip');
        $cache->remove();
    }

    /**
     * @deprecated
     * @return bool
     * @throws \Exception
     */
    public static function isBan()
    {
        return self::isBanned();
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public static function isBanned()
    {
        $ip = NetworkHelper::getClientIp();
        if ($ip == '') {
            return false;
        }
        $whiteIps = explode(';', ConfigManager::byKey('security::whiteips'));
        if (ConfigManager::byKey('security::whiteips') != '' && count($whiteIps) > 0) {
            foreach ($whiteIps as $whiteip) {
                if (NetworkHelper::netMatch($whiteip, $ip)) {
                    return false;
                }
            }
        }
        $cache = CacheManager::byKey('security::banip');
        $values = json_decode($cache->getValue('[]'), true);
        if (!is_array($values)) {
            $values = [];
        }
        $values_tmp = [];
        if (count($values) > 0) {
            foreach ($values as $value) {
                if (ConfigManager::byKey('security::bantime') >= 0 && $value['datetime'] + ConfigManager::byKey('security::bantime') < strtotime('now')) {
                    continue;
                }
                $values_tmp[] = $value;
            }
        }
        $values = $values_tmp;
        if (isset($_SESSION['failed_count']) && $_SESSION['failed_count'] >= ConfigManager::byKey('security::maxFailedLogin') && (strtotime('now') - ConfigManager::byKey('security::timeLoginFailed')) < $_SESSION['failed_datetime']) {
            $values_tmp = [];
            foreach ($values as $value) {
                if ($value['ip'] == $ip) {
                    continue;
                }
                $values_tmp[] = $value;
            }
            $values = $values_tmp;
            $values[] = ['datetime' => strtotime(DateFormat::NOW), 'ip' => NetworkHelper::getClientIp()];
            @session_start();
            $_SESSION['failed_count'] = 0;
            $_SESSION['failed_datetime'] = -1;
            @session_write_close();
        }
        CacheManager::set('security::banip', json_encode($values));
        if (!is_array($values)) {
            $values = [];
        }
        if (count($values) == 0) {
            return false;
        }
        foreach ($values as $value) {
            if ($value['ip'] != $ip) {
                continue;
            }
            if (ConfigManager::byKey('security::bantime') >= 0 && $value['datetime'] + ConfigManager::byKey('security::bantime') < strtotime('now')) {
                continue;
            }
            return true;
        }
        return false;
    }

    /**
     * @return string
     * @throws \Exception
     */
    public static function getAccessKeyForReport()
    {
        $user = self::byLogin('internal_report');
        if (!is_object($user)) {
            $user = new User();
            $user->setLogin('internal_report');
            $google2fa = new Google2FA();
            $user->setOptions('twoFactorAuthentificationSecret', $google2fa->generateSecretKey());
            $user->setOptions('twoFactorAuthentification', 1);
        }
        $user->setPassword(Utils::sha512(ConfigManager::genKey(255)));
        $user->setOptions('localOnly', 1);
        $user->setProfils(UserRight::ADMIN);
        $user->setEnable(1);
        $key = ConfigManager::genKey();
        $registerDevice = [
            Utils::sha512($key) => [
                'datetime' => date(DateFormat::FULL),
                'ip' => '127.0.0.1',
                'session_id' => 'none',
            ],
        ];
        $user->setOptions('registerDevice', $registerDevice);
        $user->save();
        return $user->getHash() . '-' . $key;
    }

    /**
     * @param bool $_enable
     * @throws \Exception
     */
    public static function supportAccess($_enable = true)
    {
        if ($_enable) {
            $user = self::byLogin('nextdom_support');
            if (!is_object($user)) {
                $user = new User();
                $user->setLogin('nextdom_support');
            }
            $user->setPassword(Utils::sha512(ConfigManager::genKey(255)));
            $user->setProfils(UserRight::ADMIN);
            $user->setEnable(1);
            $key = ConfigManager::genKey();
            $registerDevice = [
                Utils::sha512($key) => [
                    'datetime' => date(DateFormat::FULL),
                    'ip' => '127.0.0.1',
                    'session_id' => 'none',
                ],
            ];
            $user->setOptions('registerDevice', $registerDevice);
            $user->save();
            RepoMarket::supportAccess(true, $user->getHash() . '-' . $key);
        } else {
            $user = self::byLogin('nextdom_support');
            if (is_object($user)) {
                $user->remove();
            }
            RepoMarket::supportAccess(false);
        }
    }

    /**
     * @param $user
     */
    public static function storeUserInSession($user)
    {
        $_SESSION['user'] = $user;
    }

    /**
     * @return User|null
     */
    public static function getStoredUser()
    {
        if (isset($_SESSION['user'])) {
            return $_SESSION['user'];
        }
        return null;
    }

    public static function deadCmd() {
        $result = [];
        foreach (UserManager::all() as $user) {
            $cmdId = $user->getOptions('notification::cmd');
            if (!empty($cmdId) && is_object(CmdManager::byId(str_replace('#', '', $cmdId)))) {
                $result[] = ['detail' => __('Utilisateur'), 'help' => __('Commande notification utilisateur'), 'who' => $cmdId];
            }
        }
        return $result;
    }

    public static function regenerateHash(){
        foreach (self::all() as $user) {
            if($user->getProfils() != UserRight::ADMIN || $user->getOptions('doNotRotateHash',0) == 1 || !$user->isEnabled()){
                continue;
            }
            if(strtotime($user->getOptions('hashGenerated')) > strtotime('now -3 month')){
                continue;
            }
            $user->setHash('');
            $user->getHash();
            $user->save();
        }
    }
}
