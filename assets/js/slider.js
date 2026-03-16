/**
 * AdWPtracker Slider - Auto rotation des pubs
 */

(function() {
    'use strict';
    
    /**
     * Initialize slider for a zone
     */
    function initSlider(zoneElement) {
        const ads = zoneElement.querySelectorAll('.adwptracker-ad');
        
        // No slider needed if only one ad
        if (ads.length <= 1) {
            return;
        }
        
        let currentIndex = 0;
        // Get speed from zone data attribute or default to 5000ms
        const interval = parseInt(zoneElement.dataset.sliderSpeed) || 5000;
        
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
            // Hide current ad
            ads[currentIndex].style.display = 'none';
            ads[currentIndex].classList.remove('active');
            
            // Next ad
            currentIndex = (currentIndex + 1) % ads.length;
            
            // Show next ad
            ads[currentIndex].style.display = 'block';
            ads[currentIndex].classList.add('active');
            
            // Trigger impression tracking for new ad
            if (typeof trackImpression === 'function') {
                trackImpression(ads[currentIndex]);
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
            // Only init slider if zone has multiple ads
            const ads = zone.querySelectorAll('.adwptracker-ad');
            if (ads.length > 1) {
                initSlider(zone);
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
                                if (ads.length > 1) {
                                    initSlider(node);
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
