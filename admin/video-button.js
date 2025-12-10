// Typecho 编辑器视频插入按钮
(function($) {
    'use strict';

    // 等待编辑器初始化完成
    $(document).ready(function() {
        // 延迟执行,确保编辑器已加载
        setTimeout(function() {
            initVideoButton();
        }, 1000);
    });

    function initVideoButton() {
        // 检查是否是 Markdown 编辑器页面
        if ($('#wmd-button-row').length === 0) {
            return;
        }

        // 避免重复添加
        if ($('#wmd-video-button').length > 0) {
            return;
        }

        // 添加视频按钮到工具栏
        var videoButton = $('<li class="wmd-button" id="wmd-video-button" title="插入视频">' +
            '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
            '<polygon points="23 7 16 12 23 17 23 7"></polygon>' +
            '<rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect>' +
            '</svg></li>');

        $('#wmd-button-row').append(videoButton);

        // 创建视频插入弹窗
        var videoDialog = $('<div id="icefox-video-dialog" style="display:none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 999999;">' +
            '<div class="icefox-video-bg" style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.5);"></div>' +
            '<div class="icefox-video-content" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 20px; border-radius: 5px; min-width: 400px; max-width: 600px; box-shadow: 0 2px 10px rgba(0,0,0,0.3);">' +
            '<p><b>插入视频</b></p>' +
            '<p style="color: #666; font-size: 14px;">请输入视频地址(支持 mp4, webm, mov 等格式):</p>' +
            '<input type="text" id="icefox-video-url" placeholder="https://example.com/video.mp4" style="width: 100%; padding: 8px; margin: 10px 0; box-sizing: border-box; border: 1px solid #ddd; border-radius: 3px;">' +
            '<div style="text-align: right; margin-top: 15px;">' +
            '<button class="btn btn-s" id="icefox-video-cancel" style="margin-right: 5px;">取消</button>' +
            '<button class="btn btn-s primary" id="icefox-video-ok">确定</button>' +
            '</div>' +
            '</div>' +
            '</div>');

        $('body').append(videoDialog);

        // 视频按钮点击事件
        videoButton.on('click', function(e) {
            e.preventDefault();
            $('#icefox-video-dialog').fadeIn(200);
            $('#icefox-video-url').val('').focus();
        });

        // 取消按钮
        $('#icefox-video-cancel').on('click', function() {
            $('#icefox-video-dialog').fadeOut(200);
        });

        // 点击背景关闭
        $('.icefox-video-bg').on('click', function() {
            $('#icefox-video-dialog').fadeOut(200);
        });

        // 确定按钮
        $('#icefox-video-ok').on('click', function() {
            insertVideo();
        });

        // 支持回车键确认
        $('#icefox-video-url').on('keypress', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                insertVideo();
            }
        });

        // 支持 ESC 键取消
        $(document).on('keydown', function(e) {
            if (e.keyCode === 27 && $('#icefox-video-dialog').is(':visible')) {
                $('#icefox-video-dialog').fadeOut(200);
            }
        });
    }

    function insertVideo() {
        var videoUrl = $('#icefox-video-url').val().trim();

        if (!videoUrl) {
            alert('请输入视频地址');
            return;
        }

        // 验证URL格式
        if (!isValidUrl(videoUrl)) {
            alert('请输入有效的视频地址');
            return;
        }

        // 获取编辑器 textarea
        var textarea = $('#text');
        if (textarea.length === 0) {
            alert('编辑器未找到');
            return;
        }

        // 获取当前光标位置
        var cursorPos = textarea[0].selectionStart || 0;
        var textBefore = textarea.val().substring(0, cursorPos);
        var textAfter = textarea.val().substring(cursorPos);

        // 构建视频标签，对URL进行HTML转义以防止XSS
        var escapedUrl = videoUrl.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#x27;');
        var videoTag = '\n<video src="' + escapedUrl + '" controls></video>\n';

        // 插入视频标签
        textarea.val(textBefore + videoTag + textAfter);

        // 设置光标位置到视频标签后面
        var newCursorPos = cursorPos + videoTag.length;
        textarea[0].setSelectionRange(newCursorPos, newCursorPos);

        // 触发输入事件以更新预览
        textarea.trigger('input');

        // 关闭弹窗
        $('#icefox-video-dialog').fadeOut(200);
    }

    function isValidUrl(url) {
        try {
            new URL(url);
            return true;
        } catch (e) {
            return false;
        }
    }

})(jQuery);
