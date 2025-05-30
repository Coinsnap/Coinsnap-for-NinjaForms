jQuery(document).ready(function ($) {
    
    if($('#ninja_forms\\[coinsnap_provider\\]').length){
        
        setProvider();
        $('#ninja_forms\\[coinsnap_provider\\]').change(function(){
            setProvider();
        });
    }
    
    function setProvider(){
        if($('#ninja_forms\\[coinsnap_provider\\]').val() === 'coinsnap'){
            $('#row_ninja_forms\\[btcpay_server_url\\]').hide();
            $('#row_ninja_forms\\[btcpay_store_id\\]').hide();
            $('#row_ninja_forms\\[btcpay_api_key\\]').hide();
            $('#ninja_forms\\[btcpay_server_url\\]').removeAttr('required');
            $('#ninja_forms\\[btcpay_store_id\\]').removeAttr('required');
            $('#ninja_forms\\[btcpay_api_key\\]').removeAttr('required');
            
            $('#row_ninja_forms\\[coinsnap_store_id\\]').show();
            $('#row_ninja_forms\\[coinsnap_api_key\\]').show();
            $('#ninja_forms\\[coinsnap_store_id\\]').attr('required','required');
            $('#ninja_forms\\[coinsnap_api_key\\]').attr('required','required');
        }
        else {
            $('#row_ninja_forms\\[coinsnap_store_id\\]').hide();
            $('#row_ninja_forms\\[coinsnap_api_key\\]').hide();
            $('#ninja_forms\\[coinsnap_store_id\\]').removeAttr('required');
            $('#ninja_forms\\[coinsnap_api_key\\]').removeAttr('required');
            
            $('#row_ninja_forms\\[btcpay_server_url\\]').show();
            $('#row_ninja_forms\\[btcpay_store_id\\]').show();
            $('#row_ninja_forms\\[btcpay_api_key\\]').show();
            $('#ninja_forms\\[btcpay_server_url\\]').attr('required','required');
            $('#ninja_forms\\[btcpay_store_id\\]').attr('required','required');
            $('#ninja_forms\\[btcpay_api_key\\]').attr('required','required');
        }
    }
    
    function isValidUrl(serverUrl) {
        try {
            const url = new URL(serverUrl);
            if (url.protocol !== 'https:' && url.protocol !== 'http:') {
                return false;
            }
	}
        catch (e) {
            console.error(e);
            return false;
	}
        return true;
    }

    $('.btcpay-apikey-link').click(function(e) {
        e.preventDefault();
        const host = $('#ninja_forms\\[btcpay_server_url\\]').val();
	if (isValidUrl(host)) {
            let data = {
                'action': 'btcpay_server_apiurl_handler',
                'host': host,
                'apiNonce': coinsnap_ajax.nonce
            };
            
            $.post(coinsnap_ajax.ajax_url, data, function(response) {
                if (response.data.url) {
                    window.location = response.data.url;
		}
            }).fail( function() {
		alert('Error processing your request. Please make sure to enter a valid BTCPay Server instance URL.')
            });
	}
        else {
            alert('Please enter a valid url including https:// in the BTCPay Server URL input field.')
        }
    });
});

