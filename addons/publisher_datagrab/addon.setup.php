<?php

/**
 * @package     ExpressionEngine
 * @category    Publisher Datagrab
 * @author      Brian Litzinger
 * @copyright   Copyright (c) 2012, 2017 - BoldMinded, LLC
 * @link        http://boldminded.com/add-ons/publisher
 * @license
 *
 * Copyright (c) 2015. BoldMinded, LLC
 * All rights reserved.
 *
 * This source is commercial software. Use of this software requires a
 * site license for each domain it is used on. Use of this software or any
 * of its source code without express written permission in the form of
 * a purchased commercial or other license is prohibited.
 *
 * THIS CODE AND INFORMATION ARE PROVIDED "AS IS" WITHOUT WARRANTY OF ANY
 * KIND, EITHER EXPRESSED OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND/OR FITNESS FOR A
 * PARTICULAR PURPOSE.
 *
 * As part of the license agreement for this software, all modifications
 * to this source must be submitted to the original author for review and
 * possible inclusion in future releases. No compensation will be provided
 * for patches, although where possible we will attribute each contribution
 * in file revision notes. Submitting such modifications constitutes
 * assignment of copyright to the original author (Brian Litzinger and
 * BoldMinded, LLC) for such modifications. If you do not wish to assign
 * copyright to the original author, your license to  use and modify this
 * source is null and void. Use of this software constitutes your agreement
 * to this clause.
 */

if( !defined('PUBLISHER_DATAGRAB_VERSION') ) {
    define('PUBLISHER_DATAGRAB_VERSION', '2.0.0');
}

return [
    'author'      => 'BoldMinded',
    'author_url'  => 'https://boldminded.com',
    'docs_url'    => '',
    'name'        => 'Publisher - Datagrab Support',
    'description' => 'Adds Datagrab support to Publisher',
    'version'     => PUBLISHER_DATAGRAB_VERSION,
    'namespace'   => 'BoldMinded\PublisherDatagrab',
    'settings_exist' => false,
];