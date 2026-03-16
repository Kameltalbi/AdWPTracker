/**
 * AdWPtracker Admin JavaScript
 */

jQuery(document).ready(function($) {
    'use strict';
    
    /**
     * Media Uploader
     */
    $('.adwpt-upload-image').on('click', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var inputField = $('#adwpt_image_url');
        
        // Create media frame
        var mediaUploader = wp.media({
            title: 'Sélectionner une image',
            button: {
                text: 'Utiliser cette image'
            },
            multiple: false
        });
        
        // When image is selected
        mediaUploader.on('select', function() {
            var attachment = mediaUploader.state().get('selection').first().toJSON();
            inputField.val(attachment.url);
            
            // Show preview if exists
            var preview = button.siblings('.adwpt-image-preview');
            if (preview.length === 0) {
                preview = $('<div class="adwpt-image-preview" style="margin-top: 10px;"><img src="" style="max-width: 300px; height: auto; border: 1px solid #ddd; padding: 5px;"></div>');
                button.parent().append(preview);
            }
            preview.find('img').attr('src', attachment.url);
        });
        
        // Open media uploader
        mediaUploader.open();
    });
    
    /**
     * Video Uploader
     */
    $('.adwpt-upload-video').on('click', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var inputField = $('#adwpt_video_url');
        var videoTypeField = $('#adwpt_video_type');
        
        // Create media frame for video
        var mediaUploader = wp.media({
            title: 'Sélectionner une vidéo MP4',
            button: {
                text: 'Utiliser cette vidéo'
            },
            library: {
                type: 'video'
            },
            multiple: false
        });
        
        // When video is selected
        mediaUploader.on('select', function() {
            var attachment = mediaUploader.state().get('selection').first().toJSON();
            inputField.val(attachment.url);
            
            // Automatically set video type to MP4
            if (videoTypeField.length) {
                videoTypeField.val('mp4').trigger('change');
            }
            
            // Show preview
            var preview = button.siblings('.adwpt-video-preview');
            if (preview.length === 0) {
                preview = $('<div class="adwpt-video-preview" style="margin-top: 10px;"><video controls style="max-width: 400px; height: auto; border: 1px solid #ddd;"><source src="" type="video/mp4"></video></div>');
                button.parent().append(preview);
            }
            preview.find('source').attr('src', attachment.url);
            preview.find('video')[0].load();
        });
        
        // Open media uploader
        mediaUploader.open();
    });
    
    /**
     * Toggle fields based on ad type
     */
    function toggleAdTypeFields() {
        var adType = $('#adwpt_type').val();
        var imageField = $('.adwpt-image-field');
        var htmlField = $('.adwpt-html-field');
        
        if (adType === 'image') {
            imageField.show();
            htmlField.hide();
        } else {
            imageField.hide();
            htmlField.show();
        }
    }
    
    // Initial toggle
    toggleAdTypeFields();
    
    // On change
    $('#adwpt_type').on('change', toggleAdTypeFields);
    
    /**
     * Copy shortcode to clipboard
     */
    $(document).on('click', '.column-shortcode code', function() {
        var text = $(this).text();
        
        // Create temporary input
        var temp = $('<input>');
        $('body').append(temp);
        temp.val(text).select();
        document.execCommand('copy');
        temp.remove();
        
        // Show feedback
        var original = $(this).text();
        $(this).text('✓ Copié !');
        setTimeout(function() {
            $(this).text(original);
        }.bind(this), 2000);
    });
});

/**
 * Fix Double Badges in Admin Columns
 */
jQuery(document).ready(function($) {
    'use strict';
    
    /**
     * Remove duplicate content in type and status columns
     */
    function removeDuplicateBadges() {
        // For TYPE column - keep only adwpt-type-badge
        $('.wp-list-table tbody td.column-type').each(function() {
            var $cell = $(this);
            var $badge = $cell.find('.adwpt-type-badge').first();
            
            if ($badge.length) {
                // Clear cell and add only our badge
                var badgeHtml = $badge.prop('outerHTML');
                $cell.empty().html(badgeHtml);
            }
        });
        
        // For STATUS column - keep only our custom styled span
        $('.wp-list-table tbody td.column-status').each(function() {
            var $cell = $(this);
            var $badge = $cell.find('span[style*="background"]').first();
            
            if ($badge.length) {
                // Clear cell and add only our badge
                var badgeHtml = $badge.prop('outerHTML');
                $cell.empty().html(badgeHtml);
            }
        });
        
        console.log('AdWPtracker: Duplicate badges removed');
    }
    
    // Run immediately
    removeDuplicateBadges();
    
    // Run again after DOM changes (for AJAX pagination)
    setTimeout(removeDuplicateBadges, 500);
    
    // Run when page numbers clicked
    $(document).on('click', '.tablenav-pages a, .manage-column.sortable a, .manage-column.sorted a', function() {
        setTimeout(removeDuplicateBadges, 500);
    });
});
