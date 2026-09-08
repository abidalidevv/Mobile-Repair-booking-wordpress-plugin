<?php
/**
 * Brands and Models Management Class
 */
class RBF_Brands_Models_Manager {
    private $data_file;
    private $brands_data;

    /**
     * Constructor
     */
    public function __construct() {
        $this->data_file = plugin_dir_path(__FILE__) . '../brands_models_data.json';
        $this->load_data();
    }

    /**
     * Load brands and models data from JSON file
     */
    private function load_data() {
        if (file_exists($this->data_file)) {
            $json_content = file_get_contents($this->data_file);
            $this->brands_data = json_decode($json_content, true);
            
            // Check if JSON decode failed
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log('RBF Brands Manager: JSON decode error: ' . json_last_error_msg());
                $this->brands_data = [
                    'brands' => [],
                    'repair_services' => [],
                    'metadata' => []
                ];
            } else {
                // Debug logging
                error_log('RBF Brands Manager: JSON data loaded successfully');
                error_log('RBF Brands Manager: Brands count: ' . count($this->brands_data['brands'] ?? []));
                error_log('RBF Brands Manager: Repair services count: ' . count($this->brands_data['repair_services'] ?? []));
                if (isset($this->brands_data['repair_services']) && count($this->brands_data['repair_services']) > 0) {
                    $first_service = $this->brands_data['repair_services'][0];
                    error_log('RBF Brands Manager: First repair service: ' . print_r($first_service, true));
                }
            }
        } else {
            error_log('RBF Brands Manager: Data file not found: ' . $this->data_file);
            $this->brands_data = [
                'brands' => [],
                'repair_services' => [],
                'metadata' => []
            ];
        }
    }

    /**
     * Get all brands
     * 
     * @return array List of brands
     */
    public function get_brands() {
        $brands = $this->brands_data['brands'] ?? [];
        error_log('RBF Brands Manager: Returning ' . count($brands) . ' brands');
        return $brands;
    }

    /**
     * Get brand by ID
     * 
     * @param int $brand_id Brand ID
     * @return array|null Brand details
     */
    public function get_brand_by_id($brand_id) {
        foreach ($this->brands_data['brands'] as $brand) {
            if ($brand['id'] == $brand_id) {
                return $brand;
            }
        }
        return null;
    }

    /**
     * Get models for a specific brand
     * 
     * @param int $brand_id Brand ID
     * @return array List of models for the brand
     */
    public function get_models_by_brand($brand_id) {
        $brand = $this->get_brand_by_id($brand_id);
        return $brand ? $brand['models'] : [];
    }

    /**
     * Get model by ID
     * 
     * @param int $brand_id Brand ID
     * @param int $model_id Model ID
     * @return array|null Model details
     */
    public function get_model_by_id($brand_id, $model_id) {
        $models = $this->get_models_by_brand($brand_id);
        foreach ($models as $model) {
            if ($model['id'] == $model_id) {
                return $model;
            }
        }
        return null;
    }

    /**
     * Get model ID by brand name and model name
     * 
     * @param string $brand_name Brand name
     * @param string $model_name Model name
     * @return int|null Model ID
     */
    public function get_model_id_by_names($brand_name, $model_name) {
        foreach ($this->brands_data['brands'] as $brand) {
            if (strtolower($brand['name']) === strtolower($brand_name)) {
                foreach ($brand['models'] as $model) {
                    if (strtolower($model['name']) === strtolower($model_name)) {
                        return $model['id'];
                    }
                }
                break;
            }
        }
        return null;
    }

    /**
     * Get repair services
     * 
     * @return array List of repair services
     */
    public function get_repair_services() {
        $services = $this->brands_data['repair_services'] ?? [];
        error_log('RBF Brands Manager: get_repair_services called, returning ' . count($services) . ' services');
        if (count($services) > 0) {
            error_log('RBF Brands Manager: First service details: ' . print_r($services[0], true));
        }
        return $services;
    }

    /**
     * Add a new repair service
     * 
     * @param array $service_data Repair service details
     * @return bool Success status
     */
    public function add_repair_service($service_data) {
        error_log('RBF Brands Manager: add_repair_service called with data: ' . print_r($service_data, true));
        
        // Validate service data
        if (!isset($service_data['name']) || !isset($service_data['icon'])) {
            error_log('RBF Brands Manager: Missing required fields - name: ' . (isset($service_data['name']) ? 'yes' : 'no') . ', icon: ' . (isset($service_data['icon']) ? 'yes' : 'no'));
            return false;
        }

        // Generate new service ID
        $service_data['id'] = $this->get_next_repair_service_id();
        error_log('RBF Brands Manager: Generated service ID: ' . $service_data['id']);
        
        // Add service
        $this->brands_data['repair_services'][] = $service_data;
        
        // Update metadata
        $this->brands_data['metadata']['total_repair_services'] = count($this->brands_data['repair_services']);
        
        error_log('RBF Brands Manager: About to save data, total services: ' . count($this->brands_data['repair_services']));
        
        // Save to file
        return $this->save_data();
    }

    /**
     * Update a repair service
     * 
     * @param int $service_id Service ID
     * @param array $service_data New service details
     * @return bool Success status
     */
    public function update_repair_service($service_id, $service_data) {
        error_log('RBF Brands Manager: update_repair_service called with ID: ' . $service_id . ', data: ' . print_r($service_data, true));
        
        // Validate service data
        if (!isset($service_data['name']) || !isset($service_data['icon'])) {
            error_log('RBF Brands Manager: Missing required fields - name: ' . (isset($service_data['name']) ? 'yes' : 'no') . ', icon: ' . (isset($service_data['icon']) ? 'yes' : 'no'));
            return false;
        }

        // Find and update the service
        foreach ($this->brands_data['repair_services'] as &$service) {
            if (intval($service['id']) == intval($service_id)) {
                error_log('RBF Brands Manager: Found service to update: ' . print_r($service, true));
                $service = array_merge($service, $service_data);
                $service['id'] = $service_id; // Ensure ID doesn't change
                
                error_log('RBF Brands Manager: Updated service: ' . print_r($service, true));
                
                // Save to file
                return $this->save_data();
            }
        }

        error_log('RBF Brands Manager: Service with ID ' . $service_id . ' not found');
        return false;
    }

    /**
     * Delete a repair service
     * 
     * @param int $service_id Service ID
     * @return bool Success status
     */
    public function delete_repair_service($service_id) {
        foreach ($this->brands_data['repair_services'] as $key => $service) {
            if ($service['id'] == $service_id) {
                unset($this->brands_data['repair_services'][$key]);
                $this->brands_data['repair_services'] = array_values($this->brands_data['repair_services']);
                
                // Update metadata
                $this->brands_data['metadata']['total_repair_services'] = count($this->brands_data['repair_services']);
                
                // Save to file
                return $this->save_data();
            }
        }
        return false;
    }

    /**
     * Add a new brand
     * 
     * @param array $brand_data Brand details
     * @return bool Success status
     */
    public function add_brand($brand_data) {
        error_log('RBF Brands Manager: add_brand called with data: ' . print_r($brand_data, true));
        
        // Validate brand data
        if (!isset($brand_data['name']) || !isset($brand_data['logo'])) {
            error_log('RBF Brands Manager: Missing required fields - name: ' . (isset($brand_data['name']) ? 'yes' : 'no') . ', logo: ' . (isset($brand_data['logo']) ? 'yes' : 'no'));
            return false;
        }

        // Generate new ID
        $brand_data['id'] = count($this->brands_data['brands']) + 1;
        error_log('RBF Brands Manager: Generated brand ID: ' . $brand_data['id']);
        
        // Add brand
        $this->brands_data['brands'][] = $brand_data;
        
        // Update metadata
        $this->brands_data['metadata']['total_brands'] = count($this->brands_data['brands']);
        
        error_log('RBF Brands Manager: About to save data, total brands: ' . count($this->brands_data['brands']));
        
        return $this->save_data();
    }

    /**
     * Update an existing brand
     * 
     * @param int $brand_id Brand ID
     * @param array $brand_data Updated brand details
     * @return bool Success status
     */
    public function update_brand($brand_id, $brand_data) {
        error_log('RBF Brands Manager: update_brand called for ID: ' . $brand_id . ' with data: ' . print_r($brand_data, true));
        
        foreach ($this->brands_data['brands'] as &$brand) {
            if ($brand['id'] == $brand_id) {
                error_log('RBF Brands Manager: Found brand to update: ' . $brand['name']);
                $brand = array_merge($brand, $brand_data);
                error_log('RBF Brands Manager: About to save updated brand data');
                return $this->save_data();
            }
        }
        
        error_log('RBF Brands Manager: Brand with ID ' . $brand_id . ' not found');
        return false;
    }

    /**
     * Delete a brand
     * 
     * @param int $brand_id Brand ID
     * @return bool Success status
     */
    public function delete_brand($brand_id) {
        error_log('RBF Brands Manager: delete_brand called with brand_id: ' . $brand_id);
        error_log('RBF Brands Manager: Current brands count: ' . count($this->brands_data['brands']));
        
        foreach ($this->brands_data['brands'] as $key => $brand) {
            if ($brand['id'] == $brand_id) {
                error_log('RBF Brands Manager: Found brand to delete: ' . $brand['name']);
                unset($this->brands_data['brands'][$key]);
                $this->brands_data['brands'] = array_values($this->brands_data['brands']);
                
                // Update metadata
                $this->brands_data['metadata']['total_brands'] = count($this->brands_data['brands']);
                
                error_log('RBF Brands Manager: Brand deleted, new count: ' . count($this->brands_data['brands']));
                
                return $this->save_data();
            }
        }
        
        error_log('RBF Brands Manager: Brand with ID ' . $brand_id . ' not found');
        return false;
    }

    /**
     * Add a model to a brand
     * 
     * @param int $brand_id Brand ID
     * @param array $model_data Model details
     * @return bool Success status
     */
    public function add_model($brand_id, $model_data) {
        // Validate model data
        if (!isset($model_data['name']) || !isset($model_data['image'])) {
            return false;
        }

        // Find the brand
        foreach ($this->brands_data['brands'] as &$brand) {
            if ($brand['id'] == $brand_id) {
                // Generate new model ID
                $model_data['id'] = count($brand['models']) + 1;
                
                // Add model
                $brand['models'][] = $model_data;
                
                // Update metadata
                $this->brands_data['metadata']['total_models'] = 
                    array_sum(array_map(function($b) { return count($b['models']); }, $this->brands_data['brands']));
                
                // Save to file
                return $this->save_data();
            }
        }

        return false;
    }

    /**
     * Add a new model to a specific parent brand
     * 
     * @param array $model_data Model details including name, parent_brand_id, and series
     * @return bool Success status
     */
    public function add_model_by_parent($model_data) {
        if (!isset($model_data['name']) || !isset($model_data['parent_brand_id'])) {
            return false;
        }
        
        $parent_brand_id = $model_data['parent_brand_id'];
        $series = isset($model_data['series']) ? $model_data['series'] : $model_data['name'];
        
        foreach ($this->brands_data['brands'] as &$brand) {
            if ($brand['id'] == $parent_brand_id) {
                $new_model = [
                    'id' => $this->get_next_model_id(),
                    'name' => $model_data['name'],
                    'image' => 'Brands/default_model.jpg',
                    'series' => $series,
                    'parent_series' => $series
                ];
                
                $brand['models'][] = $new_model;
                
                // Update metadata
                $this->brands_data['metadata']['total_models'] = array_sum(array_map(function($b) { 
                    return count($b['models']); 
                }, $this->brands_data['brands']));
                
                return $this->save_data();
            }
        }
        
        return false;
    }

    /**
     * Update an existing model
     * 
     * @param int $model_id Model ID
     * @param array $model_data New model details including name, parent_brand_id, and series
     * @return bool Success status
     */
    public function update_model($model_id, $model_data) {
        if (!isset($model_data['name']) || !isset($model_data['parent_brand_id'])) {
            return false;
        }
        
        $new_parent_brand_id = $model_data['parent_brand_id'];
        $new_name = $model_data['name'];
        $new_series = isset($model_data['series']) ? $model_data['series'] : $new_name;
        
        $model_found = false;
        $old_model_data = null;
        
        // Find and remove the model from its current parent brand
        foreach ($this->brands_data['brands'] as &$brand) {
            foreach ($brand['models'] as $key => $model) {
                if ($model['id'] == $model_id) {
                    $old_model_data = $model;
                    unset($brand['models'][$key]);
                    $brand['models'] = array_values($brand['models']);
                    $model_found = true;
                    break 2;
                }
            }
        }
        
        if (!$model_found) {
            return false;
        }
        
        // Add the updated model to the new parent brand
        foreach ($this->brands_data['brands'] as &$brand) {
            if ($brand['id'] == $new_parent_brand_id) {
                $updated_model = [
                    'id' => $model_id,
                    'name' => $new_name,
                    'image' => $old_model_data['image'], // Keep existing image
                    'series' => $new_series,
                    'parent_series' => $new_series
                ];
                
                $brand['models'][] = $updated_model;
                
                // Update metadata
                $this->brands_data['metadata']['total_models'] = array_sum(array_map(function($b) { 
                    return count($b['models']); 
                }, $this->brands_data['brands']));
                
                return $this->save_data();
            }
        }
        
        return false;
    }

    /**
     * Delete a model from a brand
     * 
     * @param int $model_id Model ID
     * @return bool Success status
     */
    public function delete_model($model_id) {
        foreach ($this->brands_data['brands'] as &$brand) {
            foreach ($brand['models'] as $key => $model) {
                if ($model['id'] == $model_id) {
                    unset($brand['models'][$key]);
                    $brand['models'] = array_values($brand['models']);
                    
                    // Update metadata
                    $this->brands_data['metadata']['total_models'] = 
                        array_sum(array_map(function($b) { return count($b['models']); }, $this->brands_data['brands']));
                    
                    return $this->save_data();
                }
            }
        }
        return false;
    }

    /**
     * Save data back to JSON file
     * 
     * @return bool Success status
     */
    private function save_data() {
        $json_content = json_encode($this->brands_data, JSON_PRETTY_PRINT);
        $result = file_put_contents($this->data_file, $json_content);
        
        if ($result === false) {
            error_log('RBF Brands Manager: Failed to save data to ' . $this->data_file);
            error_log('RBF Brands Manager: File write error: ' . error_get_last()['message']);
        } else {
            error_log('RBF Brands Manager: Successfully saved data to ' . $this->data_file);
        }
        
        return $result !== false;
    }

    /**
     * Update metadata
     * 
     * @param array $metadata New metadata
     * @return bool Success status
     */
    public function update_metadata($metadata) {
        $this->brands_data['metadata'] = array_merge(
            $this->brands_data['metadata'], 
            $metadata
        );
        return $this->save_data();
    }

    /**
     * Get next available model ID
     * 
     * @return int Next model ID
     */
    private function get_next_model_id() {
        $max_id = 0;
        foreach ($this->brands_data['brands'] as $brand) {
            foreach ($brand['models'] as $model) {
                if ($model['id'] > $max_id) {
                    $max_id = $model['id'];
                }
            }
        }
        return $max_id + 1;
    }

    /**
     * Get next available repair service ID
     * 
     * @return int Next service ID
     */
    private function get_next_repair_service_id() {
        $max_id = 0;
        foreach ($this->brands_data['repair_services'] as $service) {
            if ($service['id'] > $max_id) {
                $max_id = $service['id'];
            }
        }
        return $max_id + 1;
    }
}
