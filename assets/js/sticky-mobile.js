/**
 * Sticky Mobile Ads - Professional Version
 * - Close button
 * - Impression tracking
 * - Click tracking
 * - Force styles override
 * - HIDE ON DESKTOP
 */

(function() {
    'use strict';
    
    /**
     * Check if desktop (>= 1024px)
     */
    function isDesktop() {
        return window.innerWidth >= 1024;
    }
    
    /**
     * Force styles on sticky footer (override theme)
     */
    function forceStyles() {
        const footer = document.getElementById('adwptracker-sticky-footer');
        if (!footer) return;
        
        // HIDE ON DESKTOP
        if (isDesktop()) {
            footer.style.display = 'none !important';
            document.body.classList.remove('adwptracker-has-sticky');
            return;
        }
        
        // Add class to body for padding
        document.body.classList.add('adwptracker-has-sticky');
        
        // Force container styles on mobile/tablet
        footer.style.cssText = `
            position: fixed !important;
            bottom: 60px !important;
            left: 0 !important;
            right: 0 !important;
            width: 100vw !important;
            max-width: 100vw !important;
            margin: 0 !important;
            padding: 10px 0 10px 0 !important;
            border: none !important;
            background: white !important;
            z-index: 999999 !important;
            box-sizing: border-box !important;
            overflow: visible !important;
            display: block !important;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.1) !important;
        `;
        
        // Force image styles
        const img = footer.querySelector('img');
        if (img) {
            img.style.cssText = `
                width: 100vw !important;
                max-width: 100vw !important;
                height: auto !important;
                display: block !important;
                margin: 0 !important;
                padding: 0 !important;
                border: none !important;
                object-fit: contain !important;
                box-sizing: border-box !important;
            `;
        }
        
        // Force link styles
        const link = footer.querySelector('a');
        if (link) {
            link.style.cssText = `
                display: block !important;
                width: 100% !important;
                max-width: 100vw !important;
                margin: 0 !important;
                padding: 0 !important;
                border: none !important;
                text-decoration: none !important;
            `;
        }
        
        // Force content styles
        const content = footer.querySelector('.adwptracker-sticky-content');
        if (content) {
            content.style.cssText = `
                width: 100% !important;
                max-width: 100vw !important;
                margin: 0 !important;
                padding: 0 !important;
                border: none !important;
                box-sizing: border-box !important;
                overflow: hidden !important;
            `;
        }
    }
    
    /**
     * Initialize sticky footer mobile
     */
    function initStickyFooterMobile() {
        const footer = document.getElementById('adwptracker-sticky-footer');
        
        if (!footer) {
            return;
        }
        
        // CRITICAL: Hide on desktop immediately
        if (isDesktop()) {
            footer.style.display = 'none';
            return;
        }
        
        // Force styles immediately
        forceStyles();
        
        // Force again after short delay (for slow themes)
        setTimeout(forceStyles, 100);
        setTimeout(forceStyles, 500);
        
        const closeBtn = footer.querySelector('.adwptracker-sticky-close');
        const content = footer.querySelector('.adwptracker-sticky-content');
        
        // Force close button styles
        if (closeBtn) {
            closeBtn.style.cssText = `
                position: absolute !important;
                top: 5px !important;
                right: 5px !important;
                width: 30px !important;
                height: 30px !important;
                background: rgba(255,255,255,0.95) !important;
                color: #333 !important;
                border: 1px solid #ddd !important;
                border-radius: 50% !important;
                font-size: 20px !important;
                font-weight: bold !important;
                line-height: 28px !important;
                text-align: center !important;
                cursor: pointer !important;
                z-index: 1000000 !important;
                display: block !important;
            `;
        }
        
        // Close button functionality
        if (closeBtn) {
            closeBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                footer.style.display = 'none';
                
                // Remove body class
                document.body.classList.remove('adwptracker-has-sticky');
                
                // Store in sessionStorage to not show again during session
                sessionStorage.setItem('adwptracker_sticky_closed', '1');
            });
        }
        
        // Check if already closed during this session
        if (sessionStorage.getItem('adwptracker_sticky_closed') === '1') {
            footer.style.display = 'none';
            return;
        }
        
        // Track impression
        if (content && typeof adwptrackerData !== 'undefined') {
            const adId = content.getAttribute('data-ad-id');
            const zoneId = content.getAttribute('data-zone-id');
            
            if (adId && zoneId) {
                // Track impression after short delay
                setTimeout(function() {
                    trackImpression(adId, zoneId);
                }, 1000);
                
                // Track clicks
                const links = content.querySelectorAll('a.adwptracker-link');
                links.forEach(function(link) {
                    link.addEventListener('click', function(e) {
                        const linkAdId = link.getAttribute('data-ad-id');
                        const linkZoneId = link.getAttribute('data-zone-id');
                        
                        if (linkAdId && linkZoneId) {
                            trackClick(linkAdId, linkZoneId);
                        }
                    });
                });
            }
        }
    }
    
    /**
     * Track impression
     */
    function trackImpression(adId, zoneId) {
        if (typeof adwptrackerData === 'undefined') {
            return;
        }
        
        const formData = new FormData();
        formData.append('action', 'adwptracker_track_impression');
        formData.append('nonce', adwptrackerData.nonce);
        formData.append('ad_id', adId);
        formData.append('zone_id', zoneId);
        
        fetch(adwptrackerData.ajax_url, {
            method: 'POST',
            credentials: 'same-origin',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            // Impression tracked successfully
        })
        .catch(error => {
            console.error('AdWPtracker: Track impression error', error);
        });
    }
    
    /**
     * Track click
     */
    function trackClick(adId, zoneId) {
        if (typeof adwptrackerData === 'undefined') {
            return;
        }
        
        const formData = new FormData();
        formData.append('action', 'adwptracker_track_click');
        formData.append('nonce', adwptrackerData.nonce);
        formData.append('ad_id', adId);
        formData.append('zone_id', zoneId);
        
        fetch(adwptrackerData.ajax_url, {
            method: 'POST',
            credentials: 'same-origin',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            // Click tracked successfully
        })
        .catch(error => {
            console.error('AdWPtracker: Track click error', error);
        });
    }
    
    // Initialize on page load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initStickyFooterMobile);
    } else {
        initStickyFooterMobile();
    }
    
    // Re-check on window resize
    window.addEventListener('resize', function() {
        const footer = document.getElementById('adwptracker-sticky-footer');
        if (!footer) return;
        
        if (isDesktop()) {
            footer.style.display = 'none';
        } else {
            if (sessionStorage.getItem('adwptracker_sticky_closed') !== '1') {
                forceStyles();
            }
        }
    });
    
})();
