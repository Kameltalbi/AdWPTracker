(function() {
    'use strict';

    const RESTART_DELAY_MS = 3000;

    let youtubeApiPromise = null;
    let vimeoApiPromise = null;

    function safeReplayVideo(video) {
        window.setTimeout(function() {
            try {
                video.currentTime = 0;
                const playResult = video.play();
                if (playResult && typeof playResult.catch === 'function') {
                    playResult.catch(function() {});
                }
            } catch (e) {
                // Ignore autoplay restrictions or transient errors.
            }
        }, RESTART_DELAY_MS);
    }

    function initMp4(scope) {
        const videos = scope.querySelectorAll('video.adwpt-video-mp4');
        videos.forEach(function(video) {
            if (video.dataset.adwptLoopInit === '1') {
                return;
            }

            video.dataset.adwptLoopInit = '1';
            video.loop = false;

            video.addEventListener('ended', function() {
                safeReplayVideo(video);
            });
        });
    }

    function loadYouTubeApi() {
        if (window.YT && window.YT.Player) {
            return Promise.resolve(window.YT);
        }
        if (youtubeApiPromise) {
            return youtubeApiPromise;
        }

        youtubeApiPromise = new Promise(function(resolve) {
            const previousHandler = window.onYouTubeIframeAPIReady;

            window.onYouTubeIframeAPIReady = function() {
                if (typeof previousHandler === 'function') {
                    previousHandler();
                }
                resolve(window.YT);
            };

            const script = document.createElement('script');
            script.src = 'https://www.youtube.com/iframe_api';
            script.async = true;
            document.head.appendChild(script);
        });

        return youtubeApiPromise;
    }

    function initYouTube(scope) {
        const iframes = scope.querySelectorAll('iframe.adwpt-video-youtube');
        if (!iframes.length) {
            return;
        }

        loadYouTubeApi().then(function() {
            iframes.forEach(function(iframe) {
                if (iframe.dataset.adwptLoopInit === '1') {
                    return;
                }

                iframe.dataset.adwptLoopInit = '1';

                // eslint-disable-next-line no-new
                new window.YT.Player(iframe, {
                    events: {
                        onStateChange: function(event) {
                            if (event.data === window.YT.PlayerState.ENDED) {
                                window.setTimeout(function() {
                                    try {
                                        event.target.seekTo(0, true);
                                        event.target.playVideo();
                                    } catch (e) {
                                        // Ignore player state errors.
                                    }
                                }, RESTART_DELAY_MS);
                            }
                        }
                    }
                });
            });
        }).catch(function() {
            // Ignore third-party API loading failures.
        });
    }

    function loadVimeoApi() {
        if (window.Vimeo && window.Vimeo.Player) {
            return Promise.resolve(window.Vimeo);
        }
        if (vimeoApiPromise) {
            return vimeoApiPromise;
        }

        vimeoApiPromise = new Promise(function(resolve, reject) {
            const script = document.createElement('script');
            script.src = 'https://player.vimeo.com/api/player.js';
            script.async = true;
            script.onload = function() {
                resolve(window.Vimeo);
            };
            script.onerror = reject;
            document.head.appendChild(script);
        });

        return vimeoApiPromise;
    }

    function initVimeo(scope) {
        const iframes = scope.querySelectorAll('iframe.adwpt-video-vimeo');
        if (!iframes.length) {
            return;
        }

        loadVimeoApi().then(function() {
            iframes.forEach(function(iframe) {
                if (iframe.dataset.adwptLoopInit === '1') {
                    return;
                }

                iframe.dataset.adwptLoopInit = '1';
                const player = new window.Vimeo.Player(iframe);

                player.on('ended', function() {
                    window.setTimeout(function() {
                        player.setCurrentTime(0).then(function() {
                            return player.play();
                        }).catch(function() {
                            // Ignore autoplay restrictions or transient errors.
                        });
                    }, RESTART_DELAY_MS);
                });
            });
        }).catch(function() {
            // Ignore third-party API loading failures.
        });
    }

    function initAll(scope) {
        initMp4(scope);
        initYouTube(scope);
        initVimeo(scope);
    }

    function setupMutationObserver() {
        if (typeof MutationObserver === 'undefined') {
            return;
        }

        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                mutation.addedNodes.forEach(function(node) {
                    if (node.nodeType !== 1) {
                        return;
                    }
                    initAll(node);
                });
            });
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    function init() {
        initAll(document);
        setupMutationObserver();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
