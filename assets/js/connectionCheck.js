jQuery(function ($) {
    
    let connectionCheckElement = '';
    
    if($('.coinsnap-notices').length){
        connectionCheckElement = '.coinsnap-notices';
    }
    
    let ajaxurl = coinsnap_ajax['ajax_url'];
    let coinsnapData = {
            action: 'coinsnap_connection_handler',
            _wpnonce: coinsnap_ajax['nonce']
    };

    jQuery.post( ajaxurl, coinsnapData, function( response ){

        connectionCheckResponse = $.parseJSON(response);
        let resultClass = (connectionCheckResponse.result === true)? 'success' : 'error';
        $connectionCheckMessage = '<div class="notice notice-'+resultClass+' is-dismissible '+resultClass+'"><p>'+ connectionCheckResponse.message +'</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button></div>';

        if(connectionCheckElement !== ''){
            $(connectionCheckElement).prepend($connectionCheckMessage);
                $('.notice-dismiss').click(function(){
                    $(this).parent().hide(500); 
                });
        }
        
        if($('#coinsnapConnectionStatus').length){
            $('#coinsnapConnectionStatus').html('<span class="'+resultClass+'">'+ connectionCheckResponse.message +'</span>');
        }
    });
    
    
    function getCookie(name) {
        const value = `; ${document.cookie}`;
        const parts = value.split(`; ${name}=`);
        if (parts.length === 2) return parts.pop().split(';').shift();
    }

    function setCookie(name, value, days) {
        const expDate = new Date(Date.now() + days * 86400000);
        const expires = "expires=" + expDate.toUTCString();
        document.cookie = name + "=" + value + ";" + expires + ";path=/";
    }
});

