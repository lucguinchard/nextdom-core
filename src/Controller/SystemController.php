<?php

/* This file is part of NextDom Software.
 *
 * NextDom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * NextDom Software is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with NextDom Software. If not, see <http://www.gnu.org/licenses/>.
 *
 * @Support <https://www.nextdom.org>
 * @Email   <admin@nextdom.org>
 * @Authors/Contributors: Sylvaner, Byackee, cyrilphoenix71, ColonelMoutarde, edgd1er, slobberbone, Astral0, DanoneKiD
 */

namespace NextDom\Controller;

use NextDom\Helpers\Status;
use NextDom\Helpers\PagesController;
use NextDom\Helpers\Render;

class SystemController extends PagesController
{

    
    public function __construct()
    {
        Status::initConnectState();
        Status::isConnectedAdminOrFail();
    }

    /**
     * Render system page
     *
     * @param Render $render Render engine
     * @param array $pageContent Page data
     *
     * @return string Content of system page
     *
     * @throws \NextDom\Exceptions\CoreException
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    public static function system(Render $render, array &$pageContent): string
    {
        $pageData = [];
        $pageData['systemCanSudo'] = \nextdom::isCapable('sudo');
        $pageContent['JS_END_POOL'][] = '/public/js/desktop/system.js';
        $pageContent['JS_END_POOL'][] = '/public/js/adminlte/utils.js';

        return $render->get('/desktop/system.html.twig', $pageContent);
    }  
    
    /**
     * Render reboot page
     *
     * @param Render $render Render engine
     * @param array $pageContent Page data
     *
     * @return string Content of reboot page
     *
     * @throws \NextDom\Exceptions\CoreException
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    public static function reboot(Render $render, array &$pageContent): string
    {
        $pageContent['JS_END_POOL'][] = '/public/js/adminlte/utils.js';

        return $render->get('/desktop/reboot.html.twig', $pageContent);
    }
    
    
    /**
     * Render shutdown page
     *
     * @param Render $render Render engine
     * @param array $pageContent Page data
     *
     * @return string Content of shutdown page
     *
     * @throws \NextDom\Exceptions\CoreException
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    public static function shutdown(Render $render, array &$pageContent): string
    {
        $pageContent['JS_END_POOL'][] = '/public/js/adminlte/utils.js';

        return $render->get('/desktop/shutdown.html.twig', $pageContent);
    }
    
    /**
     * Render osdb page
     *
     * @param Render $render Render engine
     * @param array $pageContent Page data
     *
     * @return string Content of osdb page
     *
     * @throws \NextDom\Exceptions\CoreException
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    public static function osdb(Render $render, array &$pageContent): string
    {
        global $CONFIG;

        $pageContent['adminDbConfig'] = $CONFIG['db'];
        $pageContent['JS_END_POOL'][] = '/public/js/desktop/tools/osdb.js';
        $pageContent['JS_END_POOL'][] = '/public/js/adminlte/utils.js';

        return $render->get('/desktop/tools/osdb.html.twig', $pageContent);
    }

}
