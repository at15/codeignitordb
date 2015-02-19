<?php
/**
 * CodeIgniter
 *
 * An open source application development framework for PHP 5.2.4 or newer
 *
 * NOTICE OF LICENSE
 *
 * Licensed under the Open Software License version 3.0
 *
 * This source file is subject to the Open Software License (OSL 3.0) that is
 * bundled with this package in the files license.txt / license.rst.  It is
 * also available through the world wide web at this URL:
 * http://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to obtain it
 * through the world wide web, please send an email to
 * licensing@ellislab.com so we can send you a copy immediately.
 *
 * @package        CodeIgniter
 * @author        EllisLab Dev Team
 * @copyright    Copyright (c) 2008 - 2014, EllisLab, Inc. (http://ellislab.com/)
 * @license        http://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 * @link        http://codeigniter.com
 * @since        Version 3.0
 * @filesource
 */
defined('BASEPATH') OR exit('No direct script access allowed');

$lang['migration_none_found'] = 'No migrations were found.';
$lang['migration_not_found'] = 'No migration could be found with the version number: %s.';
$lang['migration_sequence_gap'] = 'There is a gap in the migration sequence near version number: %s.';
$lang['migration_multiple_version'] = 'There are multiple migrations with the same version number: %s.';
$lang['migration_class_doesnt_exist'] = 'The migration class "%s" could not be found.';
$lang['migration_missing_up_method'] = 'The migration class "%s" is missing an "up" method.';
$lang['migration_missing_down_method'] = 'The migration class "%s" is missing a "down" method.';
$lang['migration_invalid_filename'] = 'Migration "%s" has an invalid filename.';

/* End of file migration_lang.php */
/* Location: ./system/language/english/migration_lang.php */