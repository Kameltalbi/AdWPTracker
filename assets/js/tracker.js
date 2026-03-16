/**
 * AdWPtracker - Advanced Tracking Script
 * Handles impression and click tracking with cache compatibility
 */

(function() {
    'use strict';
    
    // Tracked impressions set to prevent duplicates
    const trackedImpressions = new Set();
    
    // Tracked clicks set to prevent duplicates
    const trackedClicks = new Set();
    
    /**
     * Send AJAX request
     */
    function sendAjax(action, data, callback) {
        const formData = new FormData();
        formData.append('action', action);
        formData.append('nonce', adwptrackerData.nonce);
        
        for (const key in data) {
            if (data.hasOwnProperty(key)) {
                formData.append(key, data[key]);
            }
        }
        
        fetch(adwptrackerData.ajax_url, {
            method: 'POST',
            credentials: 'same-origin',
            body: formData
        })
        .then(response => response.json())
        .then(callback)
        .catch(error => {
            console.error('AdWPtracker tracking error:', error);
        });
    }
    
    /**
     * Track impression
     */
    function trackImpression(adElement) {
        const adId = adElement.getAttribute('data-ad-id');
        const zoneId = adElement.getAttribute('data-zone-id');
        
        if (!adId || !zoneId) {
            return;
        }
        
        // Create unique key to prevent duplicate tracking
        const trackingKey = `imp_${adId}_${zoneId}`;
        
        if (trackedImpressions.has(trackingKey)) {
            return;
        }
        
        trackedImpressions.add(trackingKey);
        
        sendAjax('adwptracker_track_impression', {
            ad_id: adId,
            zone_id: zoneId
        }, function(response) {
            if (response.success) {
                // Impression tracked successfully
            }
        });
    }
    
    /**
     * Track click
     */
    function trackClick(event, linkElement) {
        const adId = linkElement.getAttribute('data-ad-id');
        const zoneId = linkElement.getAttribute('data-zone-id');
        const targetUrl = linkElement.getAttribute('href');
        
        if (!adId || !zoneId) {
            return;
        }
        
        // Create unique key to prevent duplicate tracking
        const trackingKey = `click_${adId}_${zoneId}`;
        
        if (trackedClicks.has(trackingKey)) {
            return;
        }
        
        // Prevent default to allow tracking first
        event.preventDefault();
        
        trackedClicks.add(trackingKey);
        
        sendAjax('adwptracker_track_click', {
            ad_id: adId,
            zone_id: zoneId
        }, function(response) {
            if (response.success && targetUrl) {
                // Redirect after tracking
                const target = linkElement.getAttribute('target') || '_self';
                if (target === '_blank') {
                    window.open(targetUrl, '_blank', 'noopener,noreferrer');
                } else {
                    window.location.href = targetUrl;
                }
            }
        });
    }
    
    /**
     * Setup impression tracking using IntersectionObserver
     */
    function setupImpressionTracking() {
        // Check if IntersectionObserver is supported
        if ('IntersectionObserver' in window) {
            const observer = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    // Track when ad is 50% visible
                    if (entry.isIntersecting && entry.intersectionRatio >= 0.5) {
                        trackImpression(entry.target);
                        // Unobserve after tracking
                        observer.unobserve(entry.target);
                    }
                });
            }, {
                threshold: 0.5,
                rootMargin: '0px'
            });
            
            // Observe all ad elements
            document.querySelectorAll('.adwptracker-ad').forEach(function(ad) {
                observer.observe(ad);
            });
        } else {
            // Fallback for browsers without IntersectionObserver
            setupFallbackImpressionTracking();
        }
    }
    
    /**
     * Fallback impression tracking for older browsers
     */
    function setupFallbackImpressionTracking() {
        function checkVisibility() {
            document.querySelectorAll('.adwptracker-ad').forEach(function(ad) {
                if (isElementInViewport(ad)) {
                    trackImpression(ad);
                }
            });
        }
        
        function isElementInViewport(el) {
            const rect = el.getBoundingClientRect();
            return (
                rect.top >= 0 &&
                rect.left >= 0 &&
                rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
                rect.right <= (window.innerWidth || document.documentElement.clientWidth)
            );
        }
        
        // Check on scroll and resize
        let ticking = false;
        function onScroll() {
            if (!ticking) {
                window.requestAnimationFrame(function() {
                    checkVisibility();
                    ticking = false;
                });
                ticking = true;
            }
        }
        
        window.addEventListener('scroll', onScroll, {passive: true});
        window.addEventListener('resize', onScroll, {passive: true});
        
        // Initial check
        checkVisibility();
    }
    
    /**
     * Setup click tracking
     */
    function setupClickTracking() {
        // Use event delegation for better performance
        document.addEventListener('click', function(event) {
            const target = event.target;
            const link = target.closest('.adwptracker-link');
            
            if (link) {
                trackClick(event, link);
            }
        }, false);
    }
    
    /**
     * Initialize tracking for dynamically loaded content
     * Compatible with Elementor, AJAX, and page builders
     */
    function setupMutationObserver() {
        // Check if MutationObserver is supported
        if ('MutationObserver' in window) {
            const observer = new MutationObserver(function(mutations) {
                let shouldReinitialize = false;
                
                mutations.forEach(function(mutation) {
                    if (mutation.addedNodes.length) {
                        mutation.addedNodes.forEach(function(node) {
                            if (node.nodeType === 1) { // ELEMENT_NODE
                                if (node.classList && node.classList.contains('adwptracker-ad')) {
                                    shouldReinitialize = true;
                                } else if (node.querySelector && node.querySelector('.adwptracker-ad')) {
                                    shouldReinitialize = true;
                                }
                            }
                        });
                    }
                });
                
                if (shouldReinitialize) {
                    // Reinitialize tracking for new elements
                    setupImpressionTracking();
                }
            });
            
            // Observe the entire body for changes
            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        }
    }
    
    /**
     * Initialize all tracking
     */
    function init() {
        // Wait for DOM to be ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                setupImpressionTracking();
                setupClickTracking();
                setupMutationObserver();
            });
        } else {
            // DOM is already ready
            setupImpressionTracking();
            setupClickTracking();
            setupMutationObserver();
        }
        
        // Reinitialize on Elementor preview refresh
        if (typeof elementorFrontend !== 'undefined') {
            elementorFrontend.hooks.addAction('frontend/element_ready/global', function() {
                setTimeout(function() {
                    setupImpressionTracking();
                }, 100);
            });
        }
    }
    
    // Start initialization
    init();
    
})();
