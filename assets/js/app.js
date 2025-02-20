$(document).ready(function()
{
    setLoadingImage();

    $('#search_activate').on('click', function() {
        $('#search').show();
        $(this).hide();
        $('#q').focus();
    });

    $('.search_options_open').on('click', function() {
        $('#search #search_options').toggle();
    });
    
    $('#sort_button').on('click', function(){
        $('#sort_links').toggle();
    });

    function stripQuery(imageSource)
    {
        var index = imageSource.indexOf('?');

        if (index !== -1) {
            imageSource = imageSource.substring(0, index);
        }

        return imageSource;
    }

    function updateTimestamp(url)
    {
        return stripQuery(url) + '?t=' + new Date().getTime();
    }

    if (window.location.href.indexOf('search?q=') !== -1) {
        $('#search_activate').click();
    }

    $('#image-nav-rotate').click(function(event) {

        if (window.fullSize) {
            return;
        }

        var imageSource = stripQuery($('#slider img').attr('src'));
        var imageSourceDetail = changeImageUrl(imageSource, 'detail');
        var imageSourceRotate = changeImageUrl(imageSource, 'rotate');

        if (event.ctrlKey || event.metaKey) {
            imageSourceRotate += '?invert';
        }

        $.ajax(imageSourceRotate).done(function(content) {

            $('#slider img').attr('src', updateTimestamp(imageSourceDetail));

            $('a.image').each(function() {
                var linkHref = stripQuery($(this).attr('href'));

                if (linkHref == imageSource) {
                    // update link to the image
                    linkHref = updateTimestamp(linkHref);

                    // update thumbnail url
                    $(this).find('img').each(function()
                    {
                        $(this).attr('src', updateTimestamp($(this).attr('src')));
                    });
                }
                $(this).attr('href', linkHref);
            });
        }).fail(function()
        {
            console.log('Request for rotating failed');
        });

    });

    $('#image-nav-info').click(function() {

        // if the info block is already visible than act as a toggle
        if ($('#info').is(':visible')) {
            $('#info').hide();
            return;
        }

        $('#info_inner').html('');

        var imageSource = $('#slider img').attr('src');
        imageSource = changeImageUrl(imageSource, 'info');

        $.ajax(imageSource).done(function(content) {

            var result = JSON.parse(content);

            if (result.result == true) {
                var responseHtml = $('<table/>');
                let fileNameText = '';

                if (result.folders && result.folders.length > 0) {
                    let url = window.appRoot;
                    for (i = 0; i < result.folders.length; i++) {
                        url += '/' + result.folders[i];
                        fileNameText += '<a href="' + url + '">' + result.folders[i] + '</a> ' + ' / ';
                    }
                }
                fileNameText += result.filename;
                responseHtml.append('<tr><td>File</td><td>' + fileNameText + '</td></tr>');

                var data = result.data;

                if (data) {
                    $.each(data, function(key, val) {
                        var items = [];

                        // if (key.indexOf('Date') !== -1 && val != false) {
                        //     dateObj = new Date(val * 1000);
                        //     val = dateObj.toLocaleDateString() + ' ' + dateObj.toLocaleTimeString();
                        // }

                        if (key.indexOf('GPS') !== -1 && val != false && val.length > 0) {
                            val = '<a target="_blank" href="https://maps.google.nl/?q=' + val.join(', ') +'">' + val.join(', ') +'</a>'
                        }

                        if (key.indexOf('Keywords') !== -1 && val != false && val.length > 0) {

                            let newVal = '';
                            let url = window.appRootRalbum + '/search?q=';
                            val.forEach(function(keyword){
                                newVal += '<a class="keyword-link" href="' + url + keyword + '">' + keyword + '</a>';
                            })

                            val = newVal;
                        }

                        if (key.indexOf('File Size') !== -1 && val > 0) {
                            val = bytesToSize(val);
                        }

                        items.push('<td>' + key + '</td>');
                        items.push('<td>' + (val == false ? '' : val) + '</td>');
                        responseHtml.append($('<tr/>', {html: items.join('')}));
                    });

                }
                else {
                    responseHtml.append('<tr><td colspan="2">No additional information could be retrieved</td></tr>');
                }

            }
            else {
                var responseHtml = $('<p>Error fetching file information</p>');
            }

            $('#info_inner').append(responseHtml);

            $('#info').show();

        }).fail(function(){
            console.log('Request for fetching info failed');
        });

    });

    $('#info_close').click(function() {

        $('#info').hide();

    });

    $('#image-nav-size').click(function() {

        var fullSize = !$(this).hasClass('active');

        window.fullSize = fullSize;

        updateFullSizeButton();

        if (fullSize === true) {
            $(this).attr('title', 'Show resized version');
        }
        else {
            $(this).attr('title', 'Show original image');
        }

        $('#slider img').each(function() {
            var imageSource = $(this).attr('src');
            imageSource = changeImageUrl(imageSource, fullSize ? 'original' : 'detail');
            setLoadingImage();
            $(this).attr('src', imageSource);
        });

        $('a.image').each(function() {
            var linkHref = $(this).attr('href');
            linkHref = changeImageUrl(linkHref, fullSize ? 'original' : 'detail');
            $(this).attr('href', linkHref);
        });

        $.ajax(window.appRoot + '/?full-size=' + fullSize.toString()).done(function(content) {

        }).fail(function() {
            console.log('Request for size changing failed');
        });

        $(this).toggleClass('active');

    });

    window.currentImage = 0;
    window.totalImages = 0;
    window.images = $('a.image');

    function changeImageUrl(url, mode)
    {
        let sourceReplacements = [];

        sourceReplacements.push(window.appRootRalbum + '/detail');
        sourceReplacements.push(window.appRoot + '/cache/detail');
        sourceReplacements.push(window.appRootRalbum + '/original');
        sourceReplacements.push(window.appRootRalbum + '/info');
        sourceReplacements.push(window.appRootRalbum + '/rotate');

        sourceReplacements.forEach(function(u){
            url = url.replace(u + '/', window.appRootRalbum + '/' + mode + '/');
        });

        return url;
    }

    function startGallery(a)
    {
        $('#overlay').show();

        window.currentImage = 0;
        var counter = 0;
        window.images.each(function(){
            if (a.attr('href') == $(this).attr('href')) {
                window.currentImage = counter;
            }
            counter++;
        });

        setTimeout(function() {

            setLoadingImage();

            $('#overlay #slider img').attr('src', a.attr('href'));

            $('#image-nav-next').css({'opacity': 1, 'cursor': 'pointer'});
            $('#image-nav-prev').css({'opacity': 1, 'cursor': 'pointer'});

            /* last image is loaded */
            if (!hasNextImage()) {
                $('#image-nav-next').css({'opacity': 0.5, 'cursor': 'default'});
            }

            /* first image is loaded */
            if (!hasPreviousImage()) {
                $('#image-nav-prev').css({'opacity': 0.5, 'cursor': 'default'});
            }

        }, 50);

    }

    function hasNextImage()
    {
        return window.currentImage < window.images.length-1;
    }

    function hasPreviousImage()
    {
        return window.currentImage > 0;
    }

    function showNextImage()
    {
        if (hasNextImage()) {
            startGallery($('a.image').eq(window.currentImage + 1));
        }
    }

    function showPreviousImage()
    {
        if (hasPreviousImage()) {
            startGallery($('a.image').eq(window.currentImage - 1));
        }
    }

    function closeGallery()
    {
        $('#overlay #slider img').attr('src', window.appRoot + '/assets/images/loading.gif');
        $('#overlay').hide();
    }

    function setLoadingImage()
    {
        $('#overlay #slider img').attr('src', window.appRoot + '/assets/images/loading.gif');
    }

    $("#slider").swipe( {

        swipe:function(event, direction, distance, duration, fingerCount) {
            if (fingerCount == 1) {
                if (direction == 'left' ) {
                    showNextImage();
                }
                if (direction == 'right') {
                    showPreviousImage();
                }
            }
        },
        fingers:$.fn.swipe.fingers.ONE
    });

    $(document).keydown(function(e)
    {
        // check if gallery is open
        if (!$('#overlay').is(':visible')) {
            return;
        }

        switch (e.which) {
            case 37: // left
                showPreviousImage();
                break;
            case 38: // up
                break;
            case 39: // right
                showNextImage();
                break;
            case 40: // down
                break;
            case 27: // esc
                closeGallery();
                break;

            default:
                return; // exit this handler for other keys
        }
        e.preventDefault(); // prevent the default action (scroll / move caret)
    });

    $('#image-nav-prev').click(function(e) {
        e.preventDefault();
        showPreviousImage();
    });

    $('#image-nav-next').click(function(e) {
        e.preventDefault();
        showNextImage();
    });

    $('#image-nav-close').click(function() {
        closeGallery();
    });

    $('a.image').click(function(e) {
        e.preventDefault();
        startGallery($(this));
    });

    /* CREATE THUMBNAILS AFTER LOAD */
    var imagesToGenerate = $('a.image img[data-src]');

    var thumbnailsFailed = [];

    function generateThumbnail(image, imagesToGenerate)
    {
        $.ajax(window.appRootRalbum + '/update_thumbnail' + image.attr('data-src')).done(function(content) {
            if (content == 'success') {
                image.attr('src', window.appRoot + '/cache/thumbnail' + image.attr('data-src'));
            } else {
                image.parent().remove();
                console.log('Could not generate ' + image.attr('data-src'));
                thumbnailsFailed.push(image.attr('data-src'));
            }

            image.removeClass('loading');

            var nextImage = false;
            var nextImageObject = false;

            imagesToGenerate.each(function() {

                if (nextImage == true) {
                    nextImageObject = $(this);
                    nextImage = false;
                }

                if ($(this).attr('id') == image.attr('id')) {
                    nextImage = true;
                }
            });

            if (nextImageObject == false) {
                $('#information').html('');
                $('#information').hide();

                if (thumbnailsFailed.length > 0) {
                    var span = $('<span></span>').attr('title', thumbnailsFailed.join('\n'));
                    var imagesText = thumbnailsFailed.length == 1 ? 'image' : 'images';
                    span.html(
                        'For <strong>' + thumbnailsFailed.length + ' ' + imagesText + '</strong> it was not possible to generate a thumbnail');

                    $('#information').html(span);
                    $('#information').show();
                }
            } else {
                generateThumbnail(nextImageObject, imagesToGenerate);
            }

        }).fail(function()
        {
            console.log('Request for generation thumbnail failed');
        }).always(function()
        {
            //console.log( "complete" );
        });
    }

    if (imagesToGenerate.length > 0) {
        $('#information').show();
        $('#information').append(
            $('<span>Generating ' + imagesToGenerate.length + ' thumbnails in the background ...</span>').attr('id',
                'generating-images'));

        generateThumbnail(imagesToGenerate.first(), imagesToGenerate);
    }

    $('#system').on('click', function()
    {

        $('#log').toggle();

    });

    function updateFullSizeButton()
    {
        if (window.fullSize) {
            $('#image-nav-rotate').css({'opacity': 0.5, 'cursor': 'default'}).attr('title',
                'You can\'t rotate the original image');
        }
        else {
            $('#image-nav-rotate').css({'opacity': 1, 'cursor': 'pointer'}).attr('title', 'Click to rotate');
        }
    }

    updateFullSizeButton();

    function bytesToSize(bytes) {
        var sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
        if (bytes == 0) return '0 Byte';
        var i = parseInt(Math.floor(Math.log(bytes) / Math.log(1024)));
        return Math.round(bytes / Math.pow(1024, i), 2) + ' ' + sizes[i];
    }

    $('.play-video').on('click', function(){
        $('#video_overlay').show();
        if (!$('#video_container video').length) {
            $('#video_overlay_container #video_container').append('<video class="video_player" style="height: auto; max-height: 500px; width: 100%; max-width: ' + $( window ).width() + 'px;" id="video_player" controls><source src="" type="video/mp4"></video>');
        }
        $('#video_overlay video').attr('src', $(this).attr('data-url'));
    });

    $('#video-nav-close').on('click', function() {
        $('#video_overlay').hide();
        $('#video_overlay video').attr('src', '');
    })

    $('#search-files-header').on('click', function(){
       $('#search-files-container').toggle(function(){

           let textEl = $('#search-files-header .search-count');

           if ($('#search-files-container').is(":visible")) {
               textEl.html(textEl.html().replace('Show', 'Hide'))
           } else {
               textEl.html(textEl.html().replace('Hide', 'Show'))
           }
       });

    });

});
