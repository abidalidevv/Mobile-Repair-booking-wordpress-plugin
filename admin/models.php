<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// Get brands for dropdown
$brands = $wpdb->get_results(
    "SELECT * FROM {$wpdb->prefix}rbf_brands WHERE status = 'active' ORDER BY name ASC"
);

// Get models with their brand and series names
$models_query = "
    SELECT 
        m.*, 
        b.name as brand_name, 
        parent.name as series_name
    FROM 
        {$wpdb->prefix}rbf_models m
    JOIN 
        {$wpdb->prefix}rbf_brands b ON m.brand_id = b.id 
    LEFT JOIN 
        {$wpdb->prefix}rbf_models parent ON m.parent_id = parent.id
    WHERE 
        m.status = 'active' 
    ORDER BY 
        b.name, 
        parent.name, 
        m.name ASC
";
$models = $wpdb->get_results($models_query);

// Group models by brand and series
$organized_models = [];
foreach ($models as $model) {
    if (!isset($organized_models[$model->brand_name])) {
        $organized_models[$model->brand_name] = [];
    }
    
    // If no series, use a default 'Other Models' series
    $series_name = $model->series_name ?: 'Other Models';
    
    if (!isset($organized_models[$model->brand_name][$series_name])) {
        $organized_models[$model->brand_name][$series_name] = [];
    }
    
    $organized_models[$model->brand_name][$series_name][] = $model;
}

// Predefined brands that should have limited editing
$predefined_brands = ['Apple', 'Samsung', 'Google Pixel', 'OnePlus', 'Others'];
?>

<div class="wrap rbf-admin-page">
    <h1>
        Repair Booking Models
        <button type="button" class="button button-primary" id="add-model-btn">Add New Model</button>
    </h1>
    
    <?php foreach ($organized_models as $brand_name => $series_groups): ?>
        <div class="rbf-brand-section">
            <h2><?php echo esc_html($brand_name); ?></h2>
            
            <?php foreach ($series_groups as $series_name => $models): ?>
                <div class="rbf-series-section">
                    <h3><?php echo esc_html($series_name); ?></h3>
                    
                    <div class="rbf-models-grid">
                        <?php foreach ($models as $model): ?>
                            <div class="rbf-model-admin-card" data-model-id="<?php echo esc_attr($model->id); ?>">
                                <div class="rbf-model-admin-image">
                                    <img src="<?php echo esc_url($model->image_url); ?>" alt="<?php echo esc_attr($model->name); ?>">
                                </div>
                                <div class="rbf-model-admin-details">
                                    <h4><?php echo esc_html($model->name); ?></h4>
                                    
                                    <?php 
                                    // Determine if this is a predefined model
                                    $is_predefined = in_array($brand_name, $predefined_brands);
                                    ?>
                                    
                                    <div class="rbf-model-admin-actions">
                                        <button class="button button-secondary edit-model" 
                                                data-model-id="<?php echo esc_attr($model->id); ?>"
                                                data-model-name="<?php echo esc_attr($model->name); ?>"
                                                data-model-brand="<?php echo esc_attr($model->brand_id); ?>"
                                                data-model-image="<?php echo esc_url($model->image_url); ?>">
                                            Edit
                                        </button>
                                        <?php if (!$is_predefined): ?>
                                            <button class="button button-link-delete delete-model" 
                                                    data-model-id="<?php echo esc_attr($model->id); ?>">
                                                Delete
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
</div>

<!-- Model Edit/Add Modal -->
<div id="rbf-model-modal" style="display:none;">
    <div class="rbf-modal-content">
        <h2 id="model-modal-title">Edit Model</h2>
        <form id="rbf-model-form" enctype="multipart/form-data">
            <input type="hidden" id="model-id" name="model_id" value="">
            
            <div class="rbf-form-group">
                <label for="model-name">Model Name *</label>
                <input type="text" id="model-name" name="name" required>
            </div>
            
            <div class="rbf-form-group">
                <label for="model-brand">Brand *</label>
                <select id="model-brand" name="brand_id" required>
                    <?php foreach ($brands as $brand): ?>
                        <option value="<?php echo esc_attr($brand->id); ?>">
                            <?php echo esc_html($brand->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="rbf-form-group">
                <label for="model-image">Model Image</label>
                <div class="rbf-image-upload-container">
                    <input type="file" id="model-image-file" name="model_image" accept="image/*">
                    <input type="hidden" id="model-current-image" name="current_image_url" value="">
                    <div id="model-image-preview-container">
                        <img id="model-image-preview" src="" alt="Model Image Preview" style="display:none; max-width: 200px; max-height: 200px;">
                    </div>
                </div>
            </div>
            
            <div class="rbf-modal-actions">
                <button type="submit" class="button button-primary">Save Model</button>
                <button type="button" class="button button-secondary" id="rbf-cancel-model">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Add Model Button
    $('#add-model-btn').on('click', function() {
        $('#model-modal-title').text('Add New Model');
        $('#model-id').val('');
        $('#model-name').val('');
        $('#model-brand').prop('selectedIndex', 0);
        $('#model-current-image').val('');
        $('#model-image-file').val('');
        $('#model-image-preview').hide();
        $('#rbf-model-modal').show();
    });
    
    // Edit Model Button
    $('.edit-model').on('click', function() {
        const modelId = $(this).data('model-id');
        const modelName = $(this).data('model-name');
        const modelBrand = $(this).data('model-brand');
        const modelImage = $(this).data('model-image');
        
        $('#model-modal-title').text('Edit Model');
        $('#model-id').val(modelId);
        $('#model-name').val(modelName);
        $('#model-brand').val(modelBrand);
        $('#model-current-image').val(modelImage);
        $('#model-image-file').val('');
        
        // Show current image
        $('#model-image-preview').attr('src', modelImage).show();
        
        $('#rbf-model-modal').show();
    });
    
    // Image Preview
    $('#model-image-file').on('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(event) {
                $('#model-image-preview').attr('src', event.target.result).show();
            };
            reader.readAsDataURL(file);
        }
    });
    
    // Cancel Modal
    $('#rbf-cancel-model').on('click', function() {
        $('#rbf-model-modal').hide();
    });
    
    // Submit Model Form
    $('#rbf-model-form').on('submit', function(e) {
        e.preventDefault();
        
        // Create FormData object
        const formData = new FormData(this);
        formData.append('action', 'rbf_save_model');
        formData.append('nonce', rbf_admin_ajax.nonce);
        
        $.ajax({
            url: rbf_admin_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    alert('Model saved successfully');
                    location.reload();
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function() {
                alert('An error occurred while saving the model');
            }
        });
    });
    
    // Delete Model
    $('.delete-model').on('click', function() {
        const modelId = $(this).data('model-id');
        
        if (confirm('Are you sure you want to delete this model?')) {
            $.ajax({
                url: rbf_admin_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'rbf_delete_model',
                    nonce: rbf_admin_ajax.nonce,
                    model_id: modelId
                },
                success: function(response) {
                    if (response.success) {
                        alert('Model deleted successfully');
                        location.reload();
                    } else {
                        alert('Error: ' + response.data);
                    }
                },
                error: function() {
                    alert('An error occurred while deleting the model');
                }
            });
        }
    });
});
</script>

<style>
.rbf-brand-section {
    background: white;
    border: 1px solid #ddd;
    margin-bottom: 20px;
    border-radius: 5px;
}

.rbf-brand-section > h2 {
    background: #f5f5f5;
    padding: 10px 15px;
    margin: 0;
    border-bottom: 1px solid #ddd;
}

.rbf-series-section {
    padding: 15px;
}

.rbf-series-section > h3 {
    margin-top: 0;
    border-bottom: 1px solid #eee;
    padding-bottom: 10px;
}

.rbf-models-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
}

.rbf-model-admin-card {
    width: 250px;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 15px;
    text-align: center;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

.rbf-model-admin-image img {
    max-width: 150px;
    max-height: 150px;
    object-fit: contain;
}

.rbf-model-admin-details h4 {
    margin: 10px 0 15px;
}

.rbf-model-admin-actions {
    display: flex;
    justify-content: center;
    gap: 10px;
}

#rbf-model-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 1000;
}

.rbf-modal-content {
    background: white;
    padding: 20px;
    border-radius: 8px;
    width: 500px;
}

.rbf-form-group {
    margin-bottom: 15px;
}

.rbf-form-group label {
    display: block;
    margin-bottom: 5px;
}

.rbf-form-group input[type="text"],
.rbf-form-group input[type="file"],
.rbf-form-group select {
    width: 100%;
    padding: 8px;
}

.rbf-image-upload-container {
    border: 1px dashed #ccc;
    padding: 15px;
    text-align: center;
}

#model-image-preview-container {
    margin-top: 10px;
}

.rbf-modal-actions {
    display: flex;
    justify-content: space-between;
    margin-top: 20px;
}
</style>
