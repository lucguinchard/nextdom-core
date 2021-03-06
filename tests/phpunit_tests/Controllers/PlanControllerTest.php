<?php
/* This file is part of NextDom.
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

require_once(__DIR__ . '/../../../src/core.php');
require_once(__DIR__ . '/../libs/BaseControllerTest.php');

use NextDom\Enums\ControllerData;

class PlanControllerTest extends BaseControllerTest
{
    public function setUp(): void
    {
        $_SESSION['user'] = \NextDom\Managers\UserManager::byId(1);
    }

    public function tearDown(): void
    {
        if (isset($_SESSION['user'])) {
            unset($_SESSION['user']);
        }
    }


    public function testSimple()
    {
        $pageData = [];
        $result = \NextDom\Controller\Pages\PlanController::get($pageData);
        $this->assertArrayHasKey('planHeader', $pageData[ControllerData::JS_VARS_RAW]);
        $this->assertStringContainsString('div_displayObject', $result);
    }

    public function testPageDataVars()
    {
        $pageData = [];
        \NextDom\Controller\Pages\PlanController::get($pageData);
        $this->pageDataVars('desktop/pages/plan.html.twig', $pageData);
    }
}
