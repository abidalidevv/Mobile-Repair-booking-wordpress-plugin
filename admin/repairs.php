<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// Get models for dropdown
$models = $wpdb->get_results(
    "SELECT m.*, b.name as brand_name 
     FROM {$wpdb->prefix}rbf_models m 
     JOIN {$wpdb->prefix}rbf_brands b ON m.brand_id = b.id 
     WHERE m.status = 'active' 
     ORDER BY b.name, m.name ASC"
);

// Get repairs with their model and brand names
$repairs_query = "
    SELECT 
        r.*, 
        m.name as model_name, 
        b.name as brand_name,
        parent.name as series_name
    FROM 
        {$wpdb->prefix}rbf_repairs r
    JOIN 
        {$wpdb->prefix}rbf_models m ON r.model_id = m.id 
    JOIN 
        {$wpdb->prefix}rbf_brands b ON m.brand_id = b.id 
    LEFT JOIN 
        {$wpdb->prefix}rbf_models parent ON m.parent_id = parent.id
    WHERE 
        r.status = 'active' 
    ORDER BY 
        b.name, 
        parent.name, 
        m.name, 
        r.name ASC
";
$repairs = $wpdb->get_results($repairs_query);

// Group repairs by brand and series
$organized_repairs = [];
foreach ($repairs as $repair) {
    if (!isset($organized_repairs[$repair->brand_name])) {
        $organized_repairs[$repair->brand_name] = [];
    }
    
    // If no series, use a default 'Other' series
    $series_name = $repair->series_name ?: 'Other Models';
    
    if (!isset($organized_repairs[$repair->brand_name][$series_name])) {
        $organized_repairs[$repair->brand_name][$series_name] = [];
    }
    
    $organized_repairs[$repair->brand_name][$series_name][] = $repair;
}
?>

<div class="wrap rbf-admin-page">
    <h1>Repair Booking Repairs</h1>
    
    <!-- Database Update Notice -->
    <div class="rbf-db-update-notice" style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
        <h3 style="margin: 0 0 10px 0; color: #856404;">⚠️ Database Update Required</h3>
        <p style="margin: 0 0 15px 0; color: #856404;">
            If you're experiencing issues editing repairs (like "Failed to update repair service"), 
            your database structure may need to be updated. Click the button below to fix this.
        </p>
        <button type="button" class="button button-primary" id="rbf-update-db-btn">
            🔧 Update Database Structure
        </button>
        <div id="rbf-update-status" style="margin-top: 10px; display: none;"></div>
    </div>
    
    <?php foreach ($organized_repairs as $brand_name => $series_groups): ?>
        <div class="rbf-brand-section">
            <h2><?php echo esc_html($brand_name); ?></h2>
            
            <?php foreach ($series_groups as $series_name => $repairs): ?>
                <div class="rbf-series-section">
                    <h3><?php echo esc_html($series_name); ?></h3>
                    
                    <div class="rbf-repairs-grid">
                        <?php foreach ($repairs as $repair): ?>
                            <div class="rbf-repair-admin-card" data-repair-id="<?php echo esc_attr($repair->id); ?>">
                                <div class="rbf-repair-admin-image">
                                    <?php 
                                    // Extract icon from the repair's icon HTML
                                    preg_match('/<img[^>]+src="([^"]+)"/', $repair->icon, $matches);
                                    $icon_url = $matches[1] ?? '';
                                    ?>
                                    <img src="<?php echo esc_url($icon_url); ?>" alt="<?php echo esc_attr($repair->name); ?>">
                                </div>
                                <div class="rbf-repair-admin-details">
                                    <h4><?php echo esc_html($repair->name); ?></h4>
                                    <p class="rbf-repair-model">
                                        Model: <?php echo esc_html($repair->model_name); ?> 
                                        (<?php echo esc_html($repair->brand_name); ?>)
                                    </p>
                                    <div class="rbf-repair-meta">
                                        <span class="rbf-repair-price">AED <?php echo number_format($repair->price, 2); ?></span>
                                        <span class="rbf-repair-duration">⏱ <?php echo esc_html($repair->repair_time); ?></span>
                                    </div>
                                    
                                    <?php 
                                    // Determine if this is a predefined repair
                                    $predefined_brands = [
                                        'Apple', 'Samsung', 'Google Pixel', 'OnePlus', 'Others'
                                    ];
                                    $is_predefined = in_array($repair->brand_name, $predefined_brands);
                                    ?>
                                    
                                    <?php if (!$is_predefined): ?>
                                        <div class="rbf-repair-admin-actions">
                                            <button class="button button-secondary edit-repair" 
                                                    data-repair-id="<?php echo esc_attr($repair->id); ?>"
                                                    data-repair-name="<?php echo esc_attr($repair->name); ?>"
                                                    data-repair-model="<?php echo esc_attr($repair->model_id); ?>"
                                                    data-repair-description="<?php echo esc_attr($repair->description); ?>"
                                                    data-repair-time="<?php echo esc_attr($repair->repair_time); ?>"
                                                    data-repair-price="<?php echo esc_attr($repair->price); ?>">
                                                Edit
                                            </button>
                                            <button class="button button-link-delete delete-repair" 
                                                    data-repair-id="<?php echo esc_attr($repair->id); ?>">
                                                Delete
                                            </button>
                                        </div>
                                    <?php else: ?>
                                        <div class="rbf-repair-admin-note">
                                            <small>Predefined Repair (Limited Editing)</small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
</div>

<!-- Modal for Repair Edit -->
<div id="rbf-repair-edit-modal" style="display:none;">
    <div class="rbf-modal-content">
        <h2>Edit Repair</h2>
        <form id="rbf-repair-edit-form">
            <input type="hidden" id="edit-repair-id" name="repair_id" value="">
            <div class="rbf-form-group">
                <label for="edit-repair-name">Repair Name</label>
                <input type="text" id="edit-repair-name" name="name" required>
            </div>
            <div class="rbf-form-group">
                <label for="edit-repair-model">Model</label>
                <select id="edit-repair-model" name="model_id" required>
                    <?php foreach ($models as $model): ?>
                        <option value="<?php echo esc_attr($model->id); ?>">
                            <?php echo esc_html($model->name); ?> (<?php echo esc_html($model->brand_name); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="rbf-form-group">
                <label for="edit-repair-description">Description</label>
                <textarea id="edit-repair-description" name="description" rows="3"></textarea>
            </div>
            <div class="rbf-form-group">
                <label for="edit-repair-time">Repair Time</label>
                <input type="text" id="edit-repair-time" name="repair_time" placeholder="e.g., 1-2 hours">
            </div>
            <div class="rbf-form-group">
                <label for="edit-repair-price">Price (AED)</label>
                <input type="number" id="edit-repair-price" name="price" step="0.01" min="0" required>
            </div>
            <div class="rbf-modal-actions">
                <button type="submit" class="button button-primary">Save Changes</button>
                <button type="button" class="button button-secondary" id="rbf-cancel-edit">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Database Update Button
    $('#rbf-update-db-btn').on('click', function() {
        const $btn = $(this);
        const $status = $('#rbf-update-status');
        
        $btn.prop('disabled', true).text('🔄 Updating...');
        $status.html('<p style="color: #856404;">⏳ Updating database structure...</p>').show();
        
        $.post(rbf_admin_ajax.ajax_url, {
            action: 'rbf_update_database_structure',
            nonce: rbf_admin_ajax.nonce
        }, function(response) {
            if (response.success) {
                $status.html('<p style="color: #155724;">✅ Database structure updated successfully! The page will reload in 3 seconds...</p>');
                $('.rbf-db-update-notice').fadeOut(2000);
                setTimeout(() => location.reload(), 3000);
            } else {
                $status.html('<p style="color: #721c24;">❌ Error: ' + (response.data || 'Unknown error') + '</p>');
                $btn.prop('disabled', false).text('🔧 Update Database Structure');
            }
        }).fail(function() {
            $status.html('<p style="color: #721c24;">❌ Network error occurred. Please try again.</p>');
            $btn.prop('disabled', false).text('🔧 Update Database Structure');
        });
    });
    
    // Edit Repair
    $('.edit-repair').on('click', function() {
        const repairId = $(this).data('repair-id');
        const repairName = $(this).data('repair-name');
        const repairModel = $(this).data('repair-model');
        const repairDescription = $(this).data('repair-description');
        const repairTime = $(this).data('repair-time');
        const repairPrice = $(this).data('repair-price');
        
        $('#edit-repair-id').val(repairId);
        $('#edit-repair-name').val(repairName);
        $('#edit-repair-model').val(repairModel);
        $('#edit-repair-description').val(repairDescription);
        $('#edit-repair-time').val(repairTime);
        $('#edit-repair-price').val(repairPrice);
        
        $('#rbf-repair-edit-modal').show();
    });
    
    // Cancel Edit
    $('#rbf-cancel-edit').on('click', function() {
        $('#rbf-repair-edit-modal').hide();
    });
    
    // Submit Repair Edit
    $('#rbf-repair-edit-form').on('submit', function(e) {
        e.preventDefault();
        
        $.ajax({
            url: rbf_admin_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'rbf_save_repair',
                nonce: rbf_admin_ajax.nonce,
                repair_id: $('#edit-repair-id').val(),
                name: $('#edit-repair-name').val(),
                model_id: $('#edit-repair-model').val(),
                description: $('#edit-repair-description').val(),
                repair_time: $('#edit-repair-time').val(),
                price: $('#edit-repair-price').val()
            },
            success: function(response) {
                if (response.success) {
                    alert('Repair updated successfully');
                    location.reload();
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function() {
                alert('An error occurred while saving the repair');
            }
        });
    });
    
    // Delete Repair
    $('.delete-repair').on('click', function() {
        const repairId = $(this).data('repair-id');
        
        if (confirm('Are you sure you want to delete this repair?')) {
            $.ajax({
                url: rbf_admin_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'rbf_delete_repair',
                    nonce: rbf_admin_ajax.nonce,
                    repair_id: repairId
                },
                success: function(response) {
                    if (response.success) {
                        alert('Repair deleted successfully');
                        location.reload();
                    } else {
                        alert('Error: ' + response.data);
                    }
                },
                error: function() {
                    alert('An error occurred while deleting the repair');
                }
            });
        }
    });
});
</script>

<style>
.rbf-admin-page {
    margin: 20px 0;
}

.rbf-brand-section {
    background: white;
    border: 1px solid #ddd;
    margin-bottom: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.rbf-brand-section > h2 {
    background: linear-gradient(135deg, #017c36 0%, #05413a 100%);
    color: white;
    padding: 15px 20px;
    margin: 0;
    border-bottom: 1px solid #ddd;
    border-radius: 8px 8px 0 0;
    font-size: 20px;
    font-weight: 600;
}

.rbf-series-section {
    padding: 20px;
}

.rbf-series-section > h3 {
    margin-top: 0;
    border-bottom: 2px solid #017c36;
    padding-bottom: 10px;
    color: #017c36;
    font-size: 18px;
    font-weight: 600;
}

.rbf-repairs-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
    align-items: start;
}

.rbf-repair-admin-card {
    border: 1px solid #e0e0e0;
    border-radius: 12px;
    padding: 20px;
    text-align: center;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    background: white;
    transition: all 0.3s ease;
    display: flex;
    flex-direction: column;
    height: 100%;
}

.rbf-repair-admin-card:hover {
    box-shadow: 0 6px 20px rgba(0,0,0,0.15);
    transform: translateY(-2px);
    border-color: #017c36;
}

.rbf-repair-admin-image {
    margin-bottom: 15px;
    display: flex;
    justify-content: center;
    align-items: center;
}

.rbf-repair-admin-image img {
    max-width: 80px;
    max-height: 80px;
    object-fit: contain;
    border-radius: 8px;
    padding: 10px;
    background: #f8f9fa;
    border: 2px solid #e9ecef;
}

.rbf-repair-admin-details {
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

.rbf-repair-admin-details h4 {
    margin: 0 0 10px 0;
    color: #333;
    font-size: 16px;
    font-weight: 600;
    line-height: 1.3;
}

.rbf-repair-model {
    color: #666;
    margin-bottom: 15px;
    font-size: 13px;
    line-height: 1.4;
}

.rbf-repair-meta {
    display: flex;
    justify-content: center;
    gap: 20px;
    margin-bottom: 15px;
    flex-wrap: wrap;
}

.rbf-repair-price {
    font-weight: bold;
    color: #017c36;
    font-size: 16px;
    background: #f0f8ff;
    padding: 5px 12px;
    border-radius: 20px;
    border: 1px solid #017c36;
}

.rbf-repair-duration {
    color: #6c757d;
    font-size: 13px;
    background: #f8f9fa;
    padding: 5px 12px;
    border-radius: 20px;
    border: 1px solid #e9ecef;
}

.rbf-repair-admin-actions {
    display: flex;
    justify-content: center;
    gap: 10px;
    margin-top: auto;
    padding-top: 15px;
}

.rbf-repair-admin-actions .button {
    padding: 8px 16px;
    font-size: 12px;
    border-radius: 6px;
    font-weight: 500;
    min-width: 80px;
    height: auto;
    line-height: 1.4;
}

.rbf-repair-admin-note {
    color: #888;
    font-style: italic;
    font-size: 12px;
    margin-top: auto;
    padding-top: 15px;
    background: #f8f9fa;
    padding: 8px;
    border-radius: 6px;
    border: 1px solid #e9ecef;
}

#rbf-repair-edit-modal {
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
    padding: 40px;
    border-radius: 20px;
    width: 550px;
    max-width: 90vw;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    border: 1px solid rgba(1, 124, 54, 0.1);
    position: relative;
    overflow: hidden;
}

.rbf-modal-content::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(135deg, #017c36 0%, #05413a 100%);
}

.rbf-modal-content h2 {
    margin: 0 0 20px 0;
    color: #017c36;
    font-size: 24px;
    text-align: center;
}

.rbf-form-group {
    margin-bottom: 20px;
}

.rbf-form-group label {
    display: block;
    margin-bottom: 10px;
    font-weight: 700;
    color: #017c36;
    font-size: 14px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.rbf-form-group input, 
.rbf-form-group select,
.rbf-form-group textarea {
    width: 100%;
    padding: 16px;
    border: 2px solid #e0e0e0;
    border-radius: 12px;
    font-size: 15px;
    transition: all 0.3s ease;
    box-sizing: border-box;
    background: #f8f9fa;
}

.rbf-form-group input:focus, 
.rbf-form-group select:focus,
.rbf-form-group textarea:focus {
    outline: none;
    border-color: #017c36;
    box-shadow: 0 0 0 4px rgba(1, 124, 54, 0.1);
    background: white;
    transform: translateY(-1px);
}

.rbf-form-group input:focus, 
.rbf-form-group select:focus,
.rbf-form-group textarea:focus {
    outline: none;
    border-color: #017c36;
    box-shadow: 0 0 0 3px rgba(1, 124, 54, 0.1);
}

.rbf-modal-actions {
    display: flex;
    justify-content: space-between;
    margin-top: 30px;
    gap: 15px;
}

.rbf-modal-actions .button {
    padding: 14px 28px;
    font-size: 15px;
    border-radius: 25px;
    font-weight: 600;
    min-width: 140px;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.rbf-modal-actions .button-primary {
    background: linear-gradient(135deg, #017c36 0%, #05413a 100%);
    color: white;
}

.rbf-modal-actions .button-primary:hover {
    background: linear-gradient(135deg, #05413a 0%, #017c36 100%);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(1, 124, 54, 0.3);
}

.rbf-modal-actions .button-secondary {
    background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
    color: white;
}

.rbf-modal-actions .button-secondary:hover {
    background: linear-gradient(135deg, #495057 0%, #6c757d 100%);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(108, 117, 125, 0.3);
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .rbf-repairs-grid {
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 15px;
    }
    
    .rbf-repair-admin-card {
        padding: 15px;
    }
    
    .rbf-modal-content {
        padding: 20px;
        width: 95vw;
    }
    
    .rbf-modal-actions {
        flex-direction: column;
    }
    
    .rbf-modal-actions .button {
        width: 100%;
    }
}
</style>
