<?php
/**
 * Dummy Horde_Prefs stub.
 *
 * PHP version 5
 *
 * @category   Horde
 * @package    IMP
 * @subpackage UnitTests
 * @author     Gunnar Wrobel <wrobel@pardus.de>
 * @license    http://www.fsf.org/copyleft/lgpl.html LGPL
 * @link       http://pear.horde.org/index.php?package=Imp
 */

/**
 * Dummy Horde_Prefs stub.
 *
 * Copyright 2010 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @category   Horde
 * @package    IMP
 * @subpackage UnitTests
 * @author     Gunnar Wrobel <wrobel@pardus.de>
 * @license    http://www.fsf.org/copyleft/gpl.html GPL
 * @link       http://pear.horde.org/index.php?package=Imp
 */
class IMP_Stub_Prefs
{
    public function getValue($pref)
    {
        switch($pref) {
        case 'date_format':
            return '%x';
        case 'twentyFour':
            return true;
        }
    }
}