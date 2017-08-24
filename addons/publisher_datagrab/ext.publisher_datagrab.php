<?php

use BoldMinded\Publisher\Enum\Status;
use BoldMinded\Publisher\Model\Language;
use BoldMinded\Publisher\Service\Query;
use BoldMinded\Publisher\Service\Request;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

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

    public $settings        = [];
    public $settings_exist  = 'n';
    public $version         = PUBLISHER_DATAGRAB_VERSION;

    private $cache          = [];

    /** @var Request */
    private $requestService;

    /** @var Language */
    private $languageModel;

    /** @var Query */
    private $queryService;

    /**
     * @param mixed
     */
    public function __construct($settings = '')
    {
        // Create cache
        if ( !isset(ee()->session->cache['publisher_datagrab']))
        {
            ee()->session->cache['publisher_datagrab'] = [
                'entry_ids' => [],
                'imported' => []
            ];
        }
        $this->cache =& ee()->session->cache['publisher_datagrab'];

        $this->requestService = ee(Request::NAME);
        $this->languageModel = ee('Model')->make(Language::NAME);
        $this->queryService = ee(Query::NAME);
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
        $lang_id = $this->requestService->getDefaultLanguage()->getId();
        $status = Status::OPEN;

        // JSON imports can contain values such as "path/to/publisher_lang_id"
        $item = $this->validateProperties(array(
            'publisher_lang_id',
            'publisher_status',
            'entry_id'
        ), $item);

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

        if ($is_translation && isset($item['entry_id']))
        {
            $data['entry_id'] = $item['entry_id'];
            $eid = $data['entry_id'];
            $lid = $data['publisher_lang_id'];

            // Remove entries from Datagrab's internal array so translated entries are
            // flagged as not unique entries.
            if ($is_translation &&
                in_array($eid, $datagrab->entries) &&
                isset($this->cache['entry_ids'][$eid]) &&
                !in_array($lid, $this->cache['entry_ids'][$eid])
            ) {
                foreach ($datagrab->entries as $key => $val) {
                    if ($val === $eid) {
                        unset($datagrab->entries[$key]);
                    }
                }
            }

            // Track which entries we've updated.
            $this->cache['entry_ids'][$eid][] = $lid;
        }

        $this->cache['imported'] = $datagrab->entry_data;

        // Update Publisher's internals so it saves the imported entry fine.
        $currentLanguage = $this->languageModel->findLanguageById($data['publisher_lang_id']);
        $this->requestService->setCurrentLanguage($currentLanguage);
        $this->requestService->setCurrentStatus($data['publisher_status']);

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
            $exists = ee()->db->get_where('channel_titles', [
                'entry_id' => $data['entry_id']
            ])->num_rows();

            if ($exists) {
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
        if ($method === 'entry_submission_absolute_end' && ee()->input->get('module') === 'datagrab') {
            ee()->extensions->end_script = true;
        }
    }

    public function ajw_datagrab_rebuild_relationships_query($where)
    {
        $where['publisher_lang_id'] = $this->requestService->getCurrentLanguage()->getId();
        $where['publisher_status']  = $this->requestService->getSaveStatus();

        return ee()->db->select("child_id, order")
            ->where($where)
            ->order_by("order")
            ->get("publisher_relationships");
    }

    public function ajw_datagrab_rebuild_grid_query($where, $field_id)
    {
        $where['publisher_lang_id'] = $this->requestService->getCurrentLanguage()->getId();
        $where['publisher_status']  = $this->requestService->getSaveStatus();

        return ee()->db->select("*")
            ->from("channel_grid_field_".$field_id)
            ->where($where)
            ->order_by("row_order ASC")
            ->get();
    }

    public function ajw_datagrab_rebuild_assets_query($where)
    {
        $where['publisher_lang_id'] = $this->requestService->getCurrentLanguage()->getId();
        $where['publisher_status']  = $this->requestService->getSaveStatus();

        return ee()->db->select("file_id")
            ->from("assets_selections")
            ->where($where)
            ->order_by("sort_order")
            ->get();
    }

    public function ajw_datagrab_rebuild_store_query($where)
    {
        $where['publisher_lang_id'] = $this->requestService->getCurrentLanguage()->getId();
        $where['publisher_status']  = $this->requestService->getSaveStatus();

        return ee()->db->from("store_products")
            ->join("store_stock", "store_products.entry_id = store_stock.entry_id")
            ->where($where)
            ->get();
    }

    public function ajw_datagrab_pre_import($datagrab){}

    /**
     * Prevent entry_submission_absolute_end() from getting called after
     * the API entry_submission_end() is called.
     *
     * @param Object $datagrab DataGrab instance
     * @return void
     */
    public function ajw_datagrab_post_import($datagrab)
    {
        $entry_categories = [];

        if (!isset($datagrab->entry_data)) {
            return;
        }

        foreach ($datagrab->entry_data as $entry) {
            $lang_id = $entry['publisher_lang_id'];

            foreach ($entry['category'] as $cat_id) {
                if (!isset($entry_categories[$cat_id])) {
                    $entry_categories[$cat_id] = [$lang_id];
                }

                if (!in_array($lang_id, $entry_categories[$cat_id])) {
                    $entry_categories[$cat_id][] = $lang_id;
                }
            }
        }

        $this->handleCategories($entry_categories);
        $this->handleCategoryPosts($entry_categories, $datagrab->entries);
    }

    /**
     * @param array $entry_categories
     * @return void
     */
    private function handleCategories($entry_categories)
    {
        if (empty($entry_categories)) {
            return;
        }

        $cat_ids = array_keys($entry_categories);

        /** @var CI_DB_result $query */
        $query = ee()->db->from('categories')
            ->where_in('cat_id', $cat_ids)
            ->get();

        $categories = [];

        foreach ($query->result_array() as $row) {
            $categories[$row['cat_id']] = $row;
        }

        foreach ($entry_categories as $cat_id => $languages) {
            foreach ($languages as $lang_id) {
                $where = [
                    'cat_id' => $cat_id,
                    'publisher_lang_id' => $lang_id,
                    'publisher_status' => Status::OPEN,
                ];

                $data = $categories[$cat_id];
                $data = array_merge($data, $where);

                // Publisher isn't concerned with category structure.
                unset($data['parent_id']);
                unset($data['cat_order']);

                $this->queryService->insertOrUpdate(ee()->publisher_category->get_data_table(), $data, $where, 'cat_id');
            }
        }
    }

    /**
     *
     * @param array $entry_categories
     * return @void
     */
    private function handleCategoryPosts($entry_categories, $entry_ids)
    {
        if (empty($entry_categories)) {
            return;
        }

        /** @var CI_DB_result $query */
        $query = ee()->db->from('category_posts')
            ->where_in('entry_id', $entry_ids)
            ->get();

        $entry_categories = [];

        foreach ($query->result_array() as $row) {
            if (!isset($entry_categories[$row['entry_id']])) {
                $entry_categories[$row['entry_id']] = [];
            }

            $entry_categories[$row['entry_id']][] = $row['cat_id'];
        }

        ee()->load->model('publisher_category');

        foreach ($entry_categories as $entry_id => $categories) {
            // Simulate a normal entry save
            $data = [
                'revision_post' => [
                    'category' => $categories
                ]
            ];

            ee()->publisher_category->save_category_posts($entry_id, [], $data);
        }
    }

    public function findProperty($name, $item)
    {
        foreach ($item as $index => $value) {
            if ($index === $name || stripos($index, $name) !== FALSE) {
                return $value;
            }
        }

        return NULL;
    }

    public function validateProperties($properties, $item)
    {
        foreach ($properties as $property) {
            if (!isset($item[$property])) {
                $value = $this->findProperty($property, $item);

                if ($value !== NULL) {
                    $item[$property] = $value;
                }
            }
        }

        return $item;
    }

    /**
     * Activate Extension
     *
     * @return void
     */
    public function activate_extension()
    {
        // Setup custom settings in this array.
        $this->settings = [];

        // Add new hooks
        $ext_template = [
            'class'    => __CLASS__,
            'settings' => serialize($this->settings),
            'priority' => 5,
            'version'  => $this->version,
            'enabled'  => 'y'
        ];

        $extensions = [
            ['hook'=>'publisher_call', 'method'=>'publisher_call'],
            ['hook'=>'ajw_datagrab_pre_import', 'method'=>'ajw_datagrab_pre_import'],
            ['hook'=>'ajw_datagrab_post_import', 'method'=>'ajw_datagrab_post_import'],
            ['hook'=>'ajw_datagrab_modify_data_end', 'method'=>'ajw_datagrab_modify_data_end'],
            ['hook'=>'ajw_datagrab_validate_entry', 'method'=>'ajw_datagrab_validate_entry'],

            ['hook'=>'ajw_datagrab_rebuild_matrix_query', 'method'=>'ajw_datagrab_rebuild_matrix_query'],
            ['hook'=>'ajw_datagrab_rebuild_playa_query', 'method'=>'ajw_datagrab_rebuild_playa_query'],
            ['hook'=>'ajw_datagrab_rebuild_relationships_query', 'method'=>'ajw_datagrab_rebuild_relationships_query'],
            ['hook'=>'ajw_datagrab_rebuild_assets_query', 'method'=>'ajw_datagrab_rebuild_assets_query'],
            ['hook'=>'ajw_datagrab_rebuild_store_query', 'method'=>'ajw_datagrab_rebuild_store_query']
        ];

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
        if ($current == '' || $current == $this->version)
        {
            return FALSE;
        }
    }
}
