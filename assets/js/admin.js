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
        var textField = $('.adwpt-text-field');
        var videoField = $('.adwpt-video-field');
        
        imageField.hide();
        htmlField.hide();
        textField.hide();
        videoField.hide();

        if (adType === 'image') {
            imageField.show();
        } else if (adType === 'html') {
            htmlField.show();
        } else if (adType === 'text') {
            textField.show();
        } else if (adType === 'video') {
            videoField.show();
        }

        updateAdPreview();
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
        var code = $(this);
        var copied = function() {
            var original = code.text();
            code.text('✓ Copié !');
            setTimeout(function() {
                code.text(original);
            }, 2000);
        };

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(copied);
            return;
        }

        var temp = $('<input>');
        $('body').append(temp);
        temp.val(text).select();
        document.execCommand('copy');
        temp.remove();
        copied();
    });

    $(document).on('click', '.adwpt-row-actions-toggle', function(e) {
        e.preventDefault();
        e.stopPropagation();

        var menu = $(this).closest('.adwpt-row-actions-menu');
        $('.adwpt-row-actions-menu').not(menu).removeClass('is-open').find('.adwpt-row-actions-toggle').attr('aria-expanded', 'false');
        menu.toggleClass('is-open');
        $(this).attr('aria-expanded', menu.hasClass('is-open') ? 'true' : 'false');
    });

    $(document).on('click', function() {
        $('.adwpt-row-actions-menu').removeClass('is-open').find('.adwpt-row-actions-toggle').attr('aria-expanded', 'false');
    });

    $(document).on('keydown', function(e) {
        if (e.key === 'Escape') {
            $('.adwpt-row-actions-menu').removeClass('is-open').find('.adwpt-row-actions-toggle').attr('aria-expanded', 'false');
        }
    });

    function escapeHtml(value) {
        return $('<div>').text(value || '').html();
    }

    function updateAdPreview() {
        var preview = $('#adwpt-live-preview');
        if (!preview.length) {
            return;
        }

        var type = $('#adwpt_type').val();
        var imageUrl = $('#adwpt_image_url').val();
        var htmlCode = $('#adwpt_html_code').val();
        var textTitle = $('#adwpt_text_title').val();
        var textContent = $('#adwpt_text_content').val();
        var videoUrl = $('#adwpt_video_url').val();

        if (type === 'image' && imageUrl) {
            preview.html('<img src="' + escapeHtml(imageUrl) + '" alt="" style="display:block;max-width:100%;height:auto;margin:0 auto;border-radius:6px;">');
        } else if (type === 'text' && (textTitle || textContent)) {
            preview.html('<strong>' + escapeHtml(textTitle) + '</strong><p>' + escapeHtml(textContent) + '</p>');
        } else if (type === 'html' && htmlCode) {
            preview.html('<div style="max-height:160px;overflow:auto;">' + htmlCode + '</div>');
        } else if (type === 'video' && videoUrl) {
            preview.html('<video controls style="display:block;max-width:100%;height:auto;"><source src="' + escapeHtml(videoUrl) + '"></video>');
        } else {
            preview.html('<p class="description">Complétez le contenu pour afficher un aperçu.</p>');
        }
    }

    $('#adwpt_image_url, #adwpt_html_code, #adwpt_text_title, #adwpt_text_content, #adwpt_video_url').on('input change', updateAdPreview);
    updateAdPreview();
});
