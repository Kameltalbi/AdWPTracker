/**
 * AdWPtracker Slider - Auto rotation des pubs
 */

(function() {
    'use strict';

    function setSingleAdMode(zoneElement) {
        zoneElement.classList.remove('slider-ready');
        const ads = zoneElement.querySelectorAll('.adwptracker-ad');
        let firstVisibleSet = false;

        ads.forEach(ad => {
            const hasContent = ad && ad.innerHTML.trim() !== '';
            if (!firstVisibleSet && hasContent && ad.offsetParent !== null) {
                ad.style.display = 'block';
                ad.classList.add('active');
                firstVisibleSet = true;
            } else {
                ad.style.display = hasContent ? 'block' : 'none';
                ad.classList.remove('active');
            }
        });
    }
    
    /**
     * Get valid ads (filter out empty or removed ads)
     */
    function getValidAds(zoneElement) {
        const allAds = zoneElement.querySelectorAll('.adwptracker-ad');
        const validAds = [];
        
        allAds.forEach(ad => {
            // Check if ad has content (not empty)
            if (ad && ad.offsetParent !== null && ad.innerHTML.trim() !== '') {
                // Check if ad has actual content (image, html, text, or video)
                const hasImage = ad.querySelector('img');
                const hasHtml = ad.querySelector('.adwptracker-html-content');
                const hasText = ad.textContent.trim().length > 0;
                const hasVideo = ad.querySelector('video, iframe');
                
                if (hasImage || hasHtml || hasText || hasVideo) {
                    validAds.push(ad);
                }
            }
        });
        
        return validAds;
    }
    
    /**
     * Initialize slider for a zone
     */
    function initSlider(zoneElement) {
        let currentIndex = 0;
        // Get speed from zone data attribute or default to 5000ms
        const interval = parseInt(zoneElement.dataset.sliderSpeed) || 5000;
        
        // Get initial valid ads
        let ads = getValidAds(zoneElement);
        
        // No slider needed if only one ad or less
        if (ads.length <= 1) {
            setSingleAdMode(zoneElement);
            return;
        }

        zoneElement.classList.add('slider-ready');
        
        // Hide all ads except first
        ads.forEach((ad, index) => {
            if (index === 0) {
                ad.style.display = 'block';
                ad.classList.add('active');
            } else {
                ad.style.display = 'none';
                ad.classList.remove('active');
            }
        });
        
        // Rotation function
        function rotateAd() {
            // Refresh valid ads list (in case ads were removed)
            ads = getValidAds(zoneElement);
            
            // Stop rotation if not enough ads
            if (ads.length <= 1) {
                setSingleAdMode(zoneElement);
                return;
            }
            
            // Hide current ad if it still exists
            if (ads[currentIndex]) {
                ads[currentIndex].style.display = 'none';
                ads[currentIndex].classList.remove('active');
            }
            
            // Next ad (with bounds check)
            currentIndex = (currentIndex + 1) % ads.length;
            
            // Show next ad
            if (ads[currentIndex]) {
                ads[currentIndex].style.display = 'block';
                ads[currentIndex].classList.add('active');
                
                // Trigger impression tracking for new ad
                if (typeof trackImpression === 'function') {
                    trackImpression(ads[currentIndex]);
                }
            }
        }
        
        // Start rotation
        setInterval(rotateAd, interval);
        
        // Add navigation dots (optional)
        if (zoneElement.dataset.showDots === 'true') {
            addNavigationDots(zoneElement, ads, currentIndex);
        }
    }
    
    /**
     * Add navigation dots
     */
    function addNavigationDots(zoneElement, ads, currentIndex) {
        const dotsContainer = document.createElement('div');
        dotsContainer.className = 'adwptracker-dots';
        dotsContainer.style.cssText = 'text-align: center; margin-top: 10px;';
        
        ads.forEach((ad, index) => {
            const dot = document.createElement('span');
            dot.className = 'adwptracker-dot';
            dot.style.cssText = 'display: inline-block; width: 10px; height: 10px; border-radius: 50%; background: #ccc; margin: 0 5px; cursor: pointer;';
            
            if (index === currentIndex) {
                dot.style.background = '#333';
            }
            
            dot.addEventListener('click', function() {
                showAd(index);
            });
            
            dotsContainer.appendChild(dot);
        });
        
        zoneElement.appendChild(dotsContainer);
        
        function showAd(index) {
            ads.forEach(ad => {
                ad.style.display = 'none';
                ad.classList.remove('active');
            });
            ads[index].style.display = 'block';
            ads[index].classList.add('active');
            
            const dots = dotsContainer.querySelectorAll('.adwptracker-dot');
            dots.forEach((dot, i) => {
                dot.style.background = i === index ? '#333' : '#ccc';
            });
        }
    }
    
    /**
     * Initialize all zones with mode="all"
     */
    function initAllSliders() {
        const zones = document.querySelectorAll('.adwptracker-zone');
        
        zones.forEach(zone => {
            // Check if slider is enabled for this zone
            const sliderEnabled = zone.getAttribute('data-slider');
            
            // Only init slider if:
            // 1. Zone has multiple ads
            // 2. Slider is explicitly enabled (data-slider="true")
            const ads = zone.querySelectorAll('.adwptracker-ad');
            if (ads.length > 1 && sliderEnabled === 'true') {
                initSlider(zone);
            } else {
                setSingleAdMode(zone);
            }
        });
    }
    
    // Wait for DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAllSliders);
    } else {
        initAllSliders();
    }
    
    // Reinitialize on dynamic content (Elementor, AJAX)
    if (typeof MutationObserver !== 'undefined') {
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.addedNodes.length) {
                    mutation.addedNodes.forEach(function(node) {
                        if (node.nodeType === 1) {
                            if (node.classList && node.classList.contains('adwptracker-zone')) {
                                const ads = node.querySelectorAll('.adwptracker-ad');
                                if (ads.length > 1 && node.getAttribute('data-slider') === 'true') {
                                    initSlider(node);
                                } else {
                                    setSingleAdMode(node);
                                }
                            }
                        }
                    });
                }
            });
        });
        
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }
    
})();
