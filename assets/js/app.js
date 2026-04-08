document.addEventListener('DOMContentLoaded', function () {

    function $(selector, context) {
        return (context || document).querySelector(selector);
    }

    function $$(selector, context) {
        return Array.from((context || document).querySelectorAll(selector));
    }

    function show(el, displayType) {
        if (el) el.style.display = displayType || 'block';
    }

    function hide(el) {
        if (el) el.style.display = 'none';
    }

    function toggle(el, displayType) {
        if (!el) return;
        if (el.style.display === 'none' || el.style.display === '') {
            el.style.display = displayType || 'block';
        } else {
            el.style.display = 'none';
        }
    }

    function isVisible(el) {
        return el && el.style.display !== 'none' && el.style.display !== '';
    }

    function on(selector, event, fn, context) {
        const el = typeof selector === 'string' ? $(selector, context) : selector;
        if (el) el.addEventListener(event, fn);
    }

    function ajax(url) {
        return fetch(url).then(function (response) {
            if (!response.ok) throw new Error('Request failed: ' + url);
            const contentType = response.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                return response.json();
            }
            return response.text();
        });
    }

    setLoadingImage();

    on('#search_activate', 'click', function () {
        show($('#search'));
        hide(this);
        const q = $('#q');
        if (q) q.focus();
    });

    $$('.search_options_open').forEach(function(el) {
        el.addEventListener('click', function() {
            toggle($('#search #search_options'));
        });
    });

    on('#sort_button', 'click', function () {
        toggle($('#sort_links'));
    });

    if (window.location.href.indexOf('search?q=') !== -1) {
        const searchActivate = $('#search_activate');
        if (searchActivate) searchActivate.click();
    }

    function stripQuery(imageSource) {
        var index = imageSource.indexOf('?');
        if (index !== -1) {
            imageSource = imageSource.substring(0, index);
        }
        return imageSource;
    }

    function updateTimestamp(url) {
        return stripQuery(url) + '?t=' + new Date().getTime();
    }

    function changeImageUrl(url, mode) {
        let sourceReplacements = [
            window.appRootRalbum + '/detail',
            window.appRoot + '/cache/detail',
            window.appRootRalbum + '/original',
            window.appRootRalbum + '/info',
            window.appRootRalbum + '/rotate',
        ];

        sourceReplacements.forEach(function (u) {
            url = url.replace(u + '/', window.appRootRalbum + '/' + mode + '/');
        });

        return url;
    }

    on('#image-nav-rotate', 'click', function (event) {
        if (window.fullSize) return;

        const sliderImg = $('#slider img');
        if (!sliderImg) return;

        var imageSource = stripQuery(sliderImg.getAttribute('src'));
        var imageSourceDetail = changeImageUrl(imageSource, 'detail');
        var imageSourceRotate = changeImageUrl(imageSource, 'rotate');

        if (event.ctrlKey || event.metaKey) {
            imageSourceRotate += '?invert';
        }

        ajax(imageSourceRotate).then(function () {
            sliderImg.setAttribute('src', updateTimestamp(imageSourceDetail));

            $$('a.image').forEach(function (link) {
                var linkHref = stripQuery(link.getAttribute('href'));

                if (linkHref === imageSource) {
                    linkHref = updateTimestamp(linkHref);

                    link.querySelectorAll('img').forEach(function (img) {
                        img.setAttribute('src', updateTimestamp(img.getAttribute('src')));
                    });
                }
                link.setAttribute('href', linkHref);
            });
        }).catch(function () {
            console.log('Request for rotating failed');
        });
    });

    on('#image-nav-info', 'click', function () {
        const info = $('#info');
        const infoInner = $('#info_inner');

        if (isVisible(info)) {
            hide(info);
            return;
        }

        infoInner.innerHTML = '';

        var imageSource = $('#slider img').getAttribute('src');
        imageSource = changeImageUrl(imageSource, 'info');

        ajax(imageSource).then(function (result) {
            let responseHtml;

            if (result.result === true) {
                responseHtml = document.createElement('table');
                let fileNameText = '';

                if (result.folders && result.folders.length > 0) {
                    let url = window.appRoot;
                    for (let i = 0; i < result.folders.length; i++) {
                        url += '/' + result.folders[i];
                        fileNameText += '<a href="' + url + '">' + result.folders[i] + '</a>  / ';
                    }
                }
                fileNameText += result.filename;

                const fileRow = document.createElement('tr');
                fileRow.innerHTML = '<td>File</td><td>' + fileNameText + '</td>';
                responseHtml.appendChild(fileRow);

                const data = result.data;

                if (data) {
                    Object.entries(data).forEach(function ([key, val]) {
                        if (key.indexOf('GPS') !== -1 && val !== false && val.length > 0) {
                            val = '<a target="_blank" href="https://maps.google.nl/?q=' + val.join(', ') + '">' + val.join(', ') + '</a>';
                        }

                        if (key.indexOf('Keywords') !== -1 && val !== false && val.length > 0) {
                            let newVal = '';
                            let url = window.appRootRalbum + '/search?q=';
                            val.forEach(function (keyword) {
                                newVal += '<a class="keyword-link" href="' + url + keyword + '">' + keyword + '</a>';
                            });
                            val = newVal;
                        }

                        if (key.indexOf('File Size') !== -1 && val > 0) {
                            val = bytesToSize(val);
                        }

                        const row = document.createElement('tr');
                        row.innerHTML = '<td>' + key + '</td><td>' + (val === false ? '' : val) + '</td>';
                        responseHtml.appendChild(row);
                    });
                } else {
                    const row = document.createElement('tr');
                    row.innerHTML = '<td colspan="2">No additional information could be retrieved</td>';
                    responseHtml.appendChild(row);
                }
            } else {
                responseHtml = document.createElement('p');
                responseHtml.textContent = 'Error fetching file information';
            }

            infoInner.appendChild(responseHtml);
            show(info);
        }).catch(function () {
            console.log('Request for fetching info failed');
        });
    });

    on('#info_close', 'click', function () {
        hide($('#info'));
    });

    on('#image-nav-size', 'click', function () {
        var fullSize = !this.classList.contains('active');
        window.fullSize = fullSize;

        updateFullSizeButton();

        this.setAttribute('title', fullSize ? 'Show resized version' : 'Show original image');

        $$('#slider img').forEach(function (img) {
            var imageSource = img.getAttribute('src');
            imageSource = changeImageUrl(imageSource, fullSize ? 'original' : 'detail');
            setLoadingImage();
            img.setAttribute('src', imageSource);
        });

        $$('a.image').forEach(function (link) {
            var linkHref = link.getAttribute('href');
            linkHref = changeImageUrl(linkHref, fullSize ? 'original' : 'detail');
            link.setAttribute('href', linkHref);
        });

        ajax(window.appRoot + '/?full-size=' + fullSize.toString()).catch(function () {
            console.log('Request for size changing failed');
        });

        this.classList.toggle('active');
    });

    window.currentImage = 0;
    window.totalImages = 0;
    window.images = $$('a.image');

    function startGallery(a) {
        const overlay = $('#overlay');
        show(overlay);

        window.currentImage = 0;
        window.images.forEach(function (img, index) {
            if (a.getAttribute('href') === img.getAttribute('href')) {
                window.currentImage = index;
            }
        });

        setTimeout(function () {
            setLoadingImage();

            $('#overlay #slider img').setAttribute('src', a.getAttribute('href'));

            const navNext = $('#image-nav-next');
            const navPrev = $('#image-nav-prev');

            navNext.style.opacity = '1';
            navNext.style.cursor = 'pointer';
            navPrev.style.opacity = '1';
            navPrev.style.cursor = 'pointer';

            if (!hasNextImage()) {
                navNext.style.opacity = '0.5';
                navNext.style.cursor = 'default';
            }

            if (!hasPreviousImage()) {
                navPrev.style.opacity = '0.5';
                navPrev.style.cursor = 'default';
            }
        }, 50);
    }

    function hasNextImage() {
        return window.currentImage < window.images.length - 1;
    }

    function hasPreviousImage() {
        return window.currentImage > 0;
    }

    function showNextImage() {
        if (hasNextImage()) {
            startGallery(window.images[window.currentImage + 1]);
        }
    }

    function showPreviousImage() {
        if (hasPreviousImage()) {
            startGallery(window.images[window.currentImage - 1]);
        }
    }

    function closeGallery() {
        $('#overlay #slider img').setAttribute('src', window.appRoot + '/assets/images/loading.gif');
        hide($('#overlay'));
    }

    function setLoadingImage() {
        const img = $('#overlay #slider img');
        if (img) img.setAttribute('src', window.appRoot + '/assets/images/loading.gif');
    }

    (function () {
        const slider = document.getElementById('slider');
        if (!slider) return;
        let startX = null;

        slider.addEventListener('touchstart', function (e) {
            if (e.touches.length === 1) {
                startX = e.touches[0].clientX;
            }
        }, { passive: true });

        slider.addEventListener('touchend', function (e) {
            if (startX === null) return;

            const deltaX = e.changedTouches[0].clientX - startX;
            startX = null;

            if (Math.abs(deltaX) < 50) return;

            if (deltaX < 0) showNextImage();
            else showPreviousImage();
        }, { passive: true });
    })();

    document.addEventListener('keydown', function (e) {
        if (!isVisible($('#overlay'))) return;

        switch (e.which || e.keyCode) {
            case 37: showPreviousImage(); break;
            case 39: showNextImage(); break;
            case 27: closeGallery(); break;
            default: return;
        }
        e.preventDefault();
    });

    on('#image-nav-prev', 'click', function (e) {
        e.preventDefault();
        showPreviousImage();
    });

    on('#image-nav-next', 'click', function (e) {
        e.preventDefault();
        showNextImage();
    });

    on('#image-nav-close', 'click', function () {
        closeGallery();
    });

    $$('a.image').forEach(function (link) {
        link.addEventListener('click', function (e) {
            e.preventDefault();
            startGallery(this);
        });
    });

    var imagesToGenerate = $$('a.image img[data-src]');
    var thumbnailsFailed = [];

    function generateThumbnail(image, images) {
        ajax(window.appRootRalbum + '/update_thumbnail' + image.getAttribute('data-src')).then(function (content) {
            if (content === 'success') {
                image.setAttribute('src', window.appRoot + '/cache/thumbnail' + image.getAttribute('data-src'));
            } else {
                image.parentElement.remove();
                thumbnailsFailed.push(image.getAttribute('data-src'));
            }

            image.classList.remove('loading');

            const currentIndex = images.indexOf(image);
            const nextImage = images[currentIndex + 1] || null;

            if (!nextImage) {
                const information = $('#information');
                information.innerHTML = '';
                hide(information);

                if (thumbnailsFailed.length > 0) {
                    const span = document.createElement('span');
                    span.setAttribute('title', thumbnailsFailed.join('\n'));
                    const imagesText = thumbnailsFailed.length === 1 ? 'image' : 'images';
                    span.innerHTML = 'For <strong>' + thumbnailsFailed.length + ' ' + imagesText + '</strong> it was not possible to generate a thumbnail';
                    information.innerHTML = '';
                    information.appendChild(span);
                    show(information);
                }
            } else {
                generateThumbnail(nextImage, images);
            }
        }).catch(function () {
            console.log('Request for generation thumbnail failed');
        });
    }

    if (imagesToGenerate.length > 0) {
        const information = $('#information');
        show(information);
        const span = document.createElement('span');
        span.id = 'generating-images';
        span.textContent = 'Generating ' + imagesToGenerate.length + ' thumbnails in the background ...';
        information.appendChild(span);

        generateThumbnail(imagesToGenerate[0], imagesToGenerate);
    }

    on('#system', 'click', function () {
        toggle($('#log'));
    });

    function updateFullSizeButton() {
        const rotateBtn = $('#image-nav-rotate');
        if (!rotateBtn) return;

        if (window.fullSize) {
            rotateBtn.style.opacity = '0.5';
            rotateBtn.style.cursor = 'default';
            rotateBtn.setAttribute('title', "You can't rotate the original image");
        } else {
            rotateBtn.style.opacity = '1';
            rotateBtn.style.cursor = 'pointer';
            rotateBtn.setAttribute('title', 'Click to rotate');
        }
    }

    updateFullSizeButton();

     function bytesToSize(bytes) {
        var sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
        if (bytes === 0) return '0 Byte';
        var i = parseInt(Math.floor(Math.log(bytes) / Math.log(1024)));
        return Math.round(bytes / Math.pow(1024, i), 2) + ' ' + sizes[i];
    }

      $$('.play-video').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const videoOverlay = $('#video_overlay');
            show(videoOverlay);

            const videoContainer = $('#video_container');
            if (!videoContainer.querySelector('video')) {
                const video = document.createElement('video');
                video.className = 'video_player';
                video.id = 'video_player';
                video.controls = true;
                video.style.cssText = 'height: auto; max-height: 500px; width: 100%; max-width: ' + window.innerWidth + 'px;';

                const source = document.createElement('source');
                source.src = '';
                source.type = 'video/mp4';
                video.appendChild(source);

                $('#video_overlay_container #video_container').appendChild(video);
            }

            $('#video_overlay video').setAttribute('src', this.getAttribute('data-url'));
        });
    });

    on('#video-nav-close', 'click', function () {
        hide($('#video_overlay'));
        $('#video_overlay video').setAttribute('src', '');
    });

    on('#search-files-header', 'click', function () {
        const container = $('#search-files-container');
        toggle(container);

        const textEl = $('#search-files-header .search-count');
        if (textEl) {
            if (isVisible(container)) {
                textEl.innerHTML = textEl.innerHTML.replace('Show', 'Hide');
            } else {
                textEl.innerHTML = textEl.innerHTML.replace('Hide', 'Show');
            }
        }
    });

    if (window.location.hash) {
        let filename = decodeURIComponent(window.location.hash.substring(1));
        const photoElement = document.querySelector('[data-filename="' + filename + '"]');
        if (photoElement) {
            startGallery(photoElement);
        }
    }

});