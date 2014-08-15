<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * ExpressionEngine Publisher Datagrab Extension Class
 *
 * @package     ExpressionEngine
 * @subpackage  Extension
 * @category    Publisher
 * @author      Brian Litzinger
 * @copyright   Copyright (c) 2012, 2013 - Brian Litzinger
 * @link        http://boldminded.com/add-ons/publisher
 * @license
 *
 * Copyright (c) 2014. BoldMinded, LLC
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

class Publisher_datagrab_ext {

    public $settings        = array();
    public $description     = 'Adds Datagrab support to Publisher';
    public $docs_url        = '';
    public $name            = 'Publisher - Datagrab Support';
    public $settings_exist  = 'n';
    public $version         = '1.0';

    /**
     * Constructor
     *
     * @param   mixed   Settings array or empty string if none exist.
     */
    public function __construct($settings = '') {}

    public function ajw_datagrab_modify_data_end($data, $item)
    {
        // If the JSON/XML does not contain publisher fields, then return original data.
        if ( !isset($item['publisher_lang_id']) && !isset($item['publisher_status']))
        {
            return $data;
        }

        if ( !isset($item['entry_id']))
        {
            show_error('An entry_id is required for each entry being imported.');
        }

        $data['publisher_lang_id'] = $item['publisher_lang_id'];
        $data['publisher_status'] = $item['publisher_status'];
        $data['entry_id'] = $item['entry_id'];

        ee()->publisher_lib->lang_id = $data['publisher_lang_id'];
        ee()->publisher_lib->publisher_save_status = $data['publisher_status'];

        return $data;
    }

    public function ajw_datagrab_rebuild_query($where, $type)
    {
        $where['publisher_lang_id'] = ee()->publisher_lib->lang_id;
        $where['publisher_status'] = ee()->publisher_lib->publisher_save_status;

        return $where;
    }

    public function ajw_datagrab_validate_entry($data, $entry_id)
    {
        // If we have an entry_id, then it came from the imported $item,
        // so just return it so DataGrab does not think its a new entry.
        if (isset($data['entry_id']) && is_numeric($data['entry_id']))
        {
            $exists = ee()->db->get_where('channel_titles', array(
                'entry_id' => $data['entry_id']
            ))->num_rows();

            if ($exists)
            {
                return $data['entry_id'];
            }
        }

        return $entry_id;
    }

    public function publisher_call($method, $parameters)
    {
        if ($method === 'entry_submission_absolute_end' && ee()->input->get('module') === 'datagrab')
        {
            ee()->extensions->end_script = TRUE;
        }
    }

    public function ajw_datagrab_pre_import($datagrab){}

    /**
     * Prevent entry_submission_absolute_end() from getting called after
     * the API entry_submission_end() is called.
     *
     * @param Object $datagrab DataGrab instance
     * @return void
     */
    public function ajw_datagrab_post_import($datagrab){}


    /**
     * Activate Extension
     *
     * @return void
     */
    public function activate_extension()
    {
        // Setup custom settings in this array.
        $this->settings = array();

        // Add new hooks
        $ext_template = array(
            'class'    => __CLASS__,
            'settings' => serialize($this->settings),
            'priority' => 5,
            'version'  => $this->version,
            'enabled'  => 'y'
        );

        $extensions = array(
            array('hook'=>'publisher_call', 'method'=>'publisher_call'),
            array('hook'=>'ajw_datagrab_pre_import', 'method'=>'ajw_datagrab_pre_import'),
            array('hook'=>'ajw_datagrab_post_import', 'method'=>'ajw_datagrab_post_import'),
            array('hook'=>'ajw_datagrab_modify_data_end', 'method'=>'ajw_datagrab_modify_data_end'),
            array('hook'=>'ajw_datagrab_rebuild_query', 'method'=>'ajw_datagrab_rebuild_query'),
            array('hook'=>'ajw_datagrab_validate_entry', 'method'=>'ajw_datagrab_validate_entry')
        );

        foreach($extensions as $extension)
        {
            ee()->db->insert('extensions', array_merge($ext_template, $extension));
        }
    }

    // ----------------------------------------------------------------------

    /**
     * Disable Extension
     *
     * This method removes information from the exp_extensions table
     *
     * @return void
     */
    function disable_extension()
    {
        ee()->db->where('class', __CLASS__);
        ee()->db->delete('extensions');
    }

    // ----------------------------------------------------------------------

    /**
     * Update Extension
     *
     * This function performs any necessary db updates when the extension
     * page is visited
     *
     * @return  mixed   void on update / false if none
     */
    function update_extension($current = '')
    {
        if ($current == '' OR $current == $this->version)
        {
            return FALSE;
        }
    }

    // ----------------------------------------------------------------------
}

/* End of file ext.publisher_datagrab.php */
/* Location: /system/expressionengine/third_party/publisher_datagrab/ext.publisher_datagrab.php */
