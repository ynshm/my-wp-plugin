/**
 * LLM Traffic Optimizer - Admin JavaScript
 */

jQuery(document).ready(function($) {
    // 温度スライダーの値を表示に反映
    function updateTemperatureDisplay() {
        const value = $('#lto-temperature').val();
        $('#lto-temperature-value').text(value);
    }

    // 初期表示と変更時のイベント
    updateTemperatureDisplay();
    $('#lto-temperature').on('input', updateTemperatureDisplay);

    // フォームの送信を防止してAjaxで処理
    $('#lto-settings-form').on('submit', function(e) {
        e.preventDefault();
        return false;
    });

    // 通知メッセージを自動的に非表示
    setTimeout(function() {
        $('.notice.is-dismissible').fadeOut();
    }, 5000);

    // ダッシュボードカードの高さを揃える
    function equalizeCardHeights() {
        let maxHeight = 0;
        $('.lto-dashboard-card, .lto-settings-card').each(function() {
            if ($(this).height() > maxHeight) {
                maxHeight = $(this).height();
            }
        });

        $('.lto-dashboard-card, .lto-settings-card').height(maxHeight);
    }

    // ページのサイズ変更時に高さを調整
    $(window).on('resize', function() {
        $('.lto-dashboard-card, .lto-settings-card').css('height', 'auto');
        setTimeout(equalizeCardHeights, 100);
    });

    // ページ読み込み時に高さを調整
    setTimeout(equalizeCardHeights, 100);


    // APIキー検証
    $('#lto-validate-api-key').on('click', function() {
        const apiKey = $('#lto-openai-api-key').val();
        const resultElem = $('#lto-api-validation-result');

        if (!apiKey) {
            resultElem.html('<span style="color: red;">APIキーを入力してください</span>');
            return;
        }

        $(this).prop('disabled', true).text('検証中...');
        resultElem.html('<span style="color: blue;">APIキーを検証中...</span>');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'lto_validate_api_key',
                api_key: apiKey,
                nonce: ltoAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    resultElem.html('<span style="color: green;">' + response.data + '</span>');
                } else {
                    resultElem.html('<span style="color: red;">' + response.data + '</span>');
                }
            },
            error: function() {
                resultElem.html('<span style="color: red;">サーバーエラーが発生しました。後でもう一度お試しください。</span>');
            },
            complete: function() {
                $('#lto-validate-api-key').prop('disabled', false).text('検証と保存');
            }
        });
    });

    // モデル設定の保存
    $('#lto-save-model-settings').on('click', function() {
        const model = $('#lto-openai-model').val();
        const temperature = $('#lto-temperature').val();
        const resultElem = $('#lto-model-settings-result');

        $(this).prop('disabled', true).text('保存中...');
        resultElem.html('<span style="color: blue;">設定を保存中...</span>');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'lto_save_model_settings',
                model: model,
                temperature: temperature,
                nonce: ltoAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    resultElem.html('<span style="color: green;">' + response.data + '</span>');
                } else {
                    resultElem.html('<span style="color: red;">' + response.data + '</span>');
                }
            },
            error: function() {
                resultElem.html('<span style="color: red;">サーバーエラーが発生しました。後でもう一度お試しください。</span>');
            },
            complete: function() {
                $('#lto-save-model-settings').prop('disabled', false).text('モデル設定を保存');
            }
        });
    });検証 (from original code)
    $('#lto-validate-api-key').on('click', function() {
        const apiKey = $('input[name="lto_openai_api_key"]').val();
        const resultElement = $('#lto-api-key-validation-result');

        if (!apiKey) {
            resultElement.html('<span style="color: red;">APIキーを入力してください</span>');
            return;
        }

        resultElement.html('<span style="color: blue;">検証中...</span>');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'lto_validate_api_key',
                api_key: apiKey,
                nonce: ltoAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    resultElement.html('<span style="color: green;">有効なAPIキーです</span>');
                } else {
                    resultElement.html('<span style="color: red;">無効なAPIキー: ' + response.data + '</span>');
                }
            },
            error: function() {
                resultElement.html('<span style="color: red;">検証中にエラーが発生しました</span>');
            }
        });
    });

    // LLMS.txt再生成ボタン (from original code)
    $('#regenerate-llms-txt').on('click', function() {
        const button = $(this);
        const originalText = button.text();

        button.text('処理中...').prop('disabled', true);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'lto_regenerate_llms_txt',
                nonce: ltoAjax.nonce
            },
            timeout: 30000, // 30秒のタイムアウト設定
            success: function(response) {
                if (response.success) {
                    alert(response.data);
                } else {
                    alert('エラー: ' + response.data);
                }
                button.text(originalText).prop('disabled', false);
            },
            error: function() {
                alert('Ajax通信エラーが発生しました');
                button.text(originalText).prop('disabled', false);
            }
        });
    });

    // まとめ記事生成ボタン (from original code)
    $('#generate-summary').on('click', function() {
        const button = $(this);
        const originalText = button.text();
        const type = $('#summary_type').val();
        const categoryId = $('#summary_category').val();

        button.text('生成中...').prop('disabled', true);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'lto_generate_summary',
                type: type,
                category_id: categoryId,
                nonce: ltoAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    if (response.data.post_url) {
                        window.open(response.data.post_url, '_blank');
                    }
                } else {
                    alert('エラー: ' + response.data);
                }
                button.text(originalText).prop('disabled', false);
            },
            error: function() {
                alert('Ajax通信エラーが発生しました');
                button.text(originalText).prop('disabled', false);
            }
        });
    });
});