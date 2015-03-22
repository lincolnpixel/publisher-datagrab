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
    private $cache          = array();

    /**
     * Constructor
     *
     * @param   mixed   Settings array or empty string if none exist.
     */
    public function __construct($settings = '')
    {
        // Create cache
        if ( !isset(ee()->session->cache['publisher_datagrab']))
        {
            ee()->session->cache['publisher_datagrab'] = array();
        }
        $this->cache =& ee()->session->cache['publisher_datagrab'];
    }

    /**
     * Add publisher lang and status to Datagrabs $data array so they are added
     * to the entries when imported.
     *
     * @param $datagrab
     * @param $data
     * @param $item
     * @return mixed
     */
    public function ajw_datagrab_modify_data_end($datagrab, $data, $item)
    {
        $is_translation = FALSE;
        $lang_id = ee()->publisher_language->get_default_language_id();
        $status = PUBLISHER_STATUS_OPEN;

        // If the JSON/XML does not contain publisher fields, then return original data.
        if (isset($item['publisher_lang_id']) && isset($item['publisher_status']))
        {
            $is_translation = TRUE;
            $data['publisher_lang_id'] = $item['publisher_lang_id'];
            $data['publisher_status'] = $item['publisher_status'];
        }
        else
        {
            $data['publisher_lang_id'] = $lang_id;
            $data['publisher_status'] = $status;
        }

        if (!$is_translation && !isset($item['entry_id']))
        {
            show_error('An entry_id is required for each entry being imported.');
        }

        // Stop some of Publisher's hooks from getting called so data is saved correctly.
        // Added originally for fieldtypes/Publisher_matrix->post_save();
        ee()->publisher_lib->stop_post_save_hook = TRUE;

        if ($is_translation && isset($item['entry_id']))
        {
            $data['entry_id'] = $item['entry_id'];
            $eid = $data['entry_id'];
            $lid = $data['publisher_lang_id'];

            // Remove entries from Datagrabs internal array so translated entries are
            // flagged as not unique entries.
            if ($is_translation && in_array($eid, $datagrab->entries) && isset($this->cache[$eid]) && !in_array($lid, $this->cache[$eid])) {
                foreach ($datagrab->entries as $key => $val) {
                    if ($val === $eid) {
                        unset($datagrab->entries[$key]);
                    }
                }
            }

            // Track which entries we've updated.
            $this->cache[$eid][] = $lid;
        }

        // Update Publisher's internals so it saves the imported entry fine.
        ee()->publisher_lib->lang_id = $data['publisher_lang_id'];
        ee()->publisher_lib->publisher_save_status = $data['publisher_status'];

        return $data;
    }

    /**
     * Trick Datagrab into thinking the translated versions of entries are
     * existing entries, so it runs the update routine, not insert.
     *
     * @param $datagrab
     * @param $data
     * @param $entry_id
     * @return mixed
     */
    public function ajw_datagrab_validate_entry($datagrab, $data, $entry_id)
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

    /**
     * Disable Publisher's internal api call method if its a Datagrab request.
     *
     * @param $method
     * @param $parameters
     */
    public function publisher_call($method, $parameters)
    {
        if ($method === 'entry_submission_absolute_end' && ee()->input->get('module') === 'datagrab')
        {
            ee()->extensions->end_script = TRUE;
        }
    }

    public function ajw_datagrab_rebuild_matrix_query($where)
    {
        $where['publisher_lang_id'] = ee()->publisher_lib->lang_id;
        $where['publisher_status']  = ee()->publisher_lib->publisher_save_status;

        return ee()->db->select("*")
            ->where($where)
            ->order_by("row_order")
            ->get("exp_matrix_data");
    }

    public function ajw_datagrab_rebuild_playa_query($where)
    {
        $where['publisher_lang_id'] = ee()->publisher_lib->lang_id;
        $where['publisher_status'] = ee()->publisher_lib->publisher_save_status;

        return ee()->db->select("child_entry_id")
            ->where($where)
            ->get("exp_playa_relationships");
    }

    public function ajw_datagrab_rebuild_relationships_query($where)
    {
        $where['publisher_lang_id'] = ee()->publisher_lib->lang_id;
        $where['publisher_status'] = ee()->publisher_lib->publisher_save_status;

        return ee()->db->select("child_id, order")
            ->db->where($where)
            ->db->order_by("order")
            ->db->get("exp_publisher_relationships");
    }

    public function ajw_datagrab_rebuild_grid_query($where, $field_id)
    {
        $where['publisher_lang_id'] = ee()->publisher_lib->lang_id;
        $where['publisher_status'] = ee()->publisher_lib->publisher_save_status;

        return ee()->db->select("*")
            ->db->from("exp_channel_grid_field_".$field_id)
            ->db->where($where)
            ->db->order_by("row_order ASC")
            ->db->get();
    }

    public function ajw_datagrab_rebuild_assets_query($where)
    {
        $where['publisher_lang_id'] = ee()->publisher_lib->lang_id;
        $where['publisher_status'] = ee()->publisher_lib->publisher_save_status;

        return ee()->db->select("file_id")
            ->db->from("exp_assets_selections")
            ->db->where($where)
            ->db->order_by("sort_order")
            ->EE->db->get();
    }

    public function ajw_datagrab_rebuild_store_query($where)
    {
        $where['publisher_lang_id'] = ee()->publisher_lib->lang_id;
        $where['publisher_status'] = ee()->publisher_lib->publisher_save_status;

        return ee()->db->from("exp_store_products")
            ->db->join("exp_store_stock", "exp_store_products.entry_id = exp_store_stock.entry_id")
            ->db->where($where)
            ->db->get();
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
            array('hook'=>'ajw_datagrab_validate_entry', 'method'=>'ajw_datagrab_validate_entry'),

            array('hook'=>'ajw_datagrab_rebuild_matrix_query', 'method'=>'ajw_datagrab_rebuild_matrix_query'),
            array('hook'=>'ajw_datagrab_rebuild_playa_query', 'method'=>'ajw_datagrab_rebuild_playa_query'),
            array('hook'=>'ajw_datagrab_rebuild_relationships_query', 'method'=>'ajw_datagrab_rebuild_relationships_query'),
            array('hook'=>'ajw_datagrab_rebuild_assets_query', 'method'=>'ajw_datagrab_rebuild_assets_query'),
            array('hook'=>'ajw_datagrab_rebuild_store_query', 'method'=>'ajw_datagrab_rebuild_store_query')
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