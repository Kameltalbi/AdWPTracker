/**
 * Force Responsive - Override theme styles
 * Makes ALL ads responsive on mobile
 */

(function() {
    'use strict';
    
    function forceAllAdsResponsive() {
        // Force all ad containers
        const ads = document.querySelectorAll('.adwptracker-ad');
        
        ads.forEach(function(ad) {
            ad.style.cssText = `
                width: 100% !important;
                max-width: 100vw !important;
                overflow: hidden !important;
                box-sizing: border-box !important;
                margin: 0 !important;
                padding: 0 !important;
            `;
            
            // Force all images inside
            const images = ad.querySelectorAll('img');
            images.forEach(function(img) {
                img.style.cssText = `
                    width: 100% !important;
                    max-width: 100vw !important;
                    height: auto !important;
                    display: block !important;
                    margin: 0 !important;
                    padding: 0 !important;
                    border: none !important;
                    object-fit: contain !important;
                    box-sizing: border-box !important;
                `;
            });
            
            // Force all links
            const links = ad.querySelectorAll('a');
            links.forEach(function(link) {
                link.style.cssText = `
                    display: block !important;
                    width: 100% !important;
                    max-width: 100vw !important;
                    margin: 0 !important;
                    padding: 0 !important;
                    box-sizing: border-box !important;
                `;
            });
        });
        
        console.log('AdWPtracker: Forced responsive on ' + ads.length + ' ads');
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
