/**
 * Force Responsive - Override theme styles
 * Makes ALL ads responsive on mobile
 */

(function() {
    'use strict';
    
    function forceAllAdsResponsive() {
        // Keep banner formats while preventing overflow.
        const ads = document.querySelectorAll('.adwptracker-ad');
        
        ads.forEach(function(ad) {
            ad.style.maxWidth = '100%';
            ad.style.boxSizing = 'border-box';
            ad.style.overflow = 'hidden';
            
            // Do not stretch fixed-size creatives (728x90, etc.).
            const images = ad.querySelectorAll('img');
            images.forEach(function(img) {
                img.style.width = 'auto';
                img.style.maxWidth = '100%';
                img.style.height = 'auto';
                img.style.display = 'block';
                img.style.marginLeft = 'auto';
                img.style.marginRight = 'auto';
                img.style.boxSizing = 'border-box';
            });
            
            // Keep links as wrappers without forcing full width.
            const links = ad.querySelectorAll('a');
            links.forEach(function(link) {
                link.style.display = 'block';
                link.style.maxWidth = '100%';
                link.style.boxSizing = 'border-box';
            });
        });
        
        console.log('AdWPtracker: Responsive guard applied to ' + ads.length + ' ads');
    }
    
    // Run immediately
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', forceAllAdsResponsive);
    } else {
        forceAllAdsResponsive();
    }
    
    // Run again after delays (for slow themes)
    setTimeout(forceAllAdsResponsive, 100);
    setTimeout(forceAllAdsResponsive, 500);
    setTimeout(forceAllAdsResponsive, 1000);
    
    // Run on resize
    window.addEventListener('resize', forceAllAdsResponsive);
    
})();
