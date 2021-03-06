<?php
/**
 * Setup autoloading for the tests.
 *
 * @category   Horde
 * @copyright  2011-2014 Horde LLC
 * @internal
 * @license    http://www.horde.org/licenses/gpl GPL
 * @package    Passwd
 * @subpackage UnitTests
 */

Horde_Test_Autoload::addPrefix('Passwd', __DIR__ . '/../../lib');

require_once __DIR__ . '/TestCase.php';
