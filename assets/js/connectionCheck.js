jQuery(function ($) {
    
    let connectionCheckElement = '';
    
    if($('.coinsnap-notices').length){
        connectionCheckElement = '.coinsnap-notices';
    }
    
    let ajaxurl = coinsnap_ajax['ajax_url'];
    let coinsnapData = {
            action: 'coinsnapnf_connection_handler',
            _wpnonce: coinsnap_ajax['nonce']
    };
    // In the form builder the server also checks this form's webhook (admins only, nonce checked).
    const pageParams = new URLSearchParams(window.location.search);
    if (pageParams.get('page') === 'ninja-forms' && /^\d+$/.test(pageParams.get('form_id') || '')) {
        coinsnapData.form_id = pageParams.get('form_id');
    }

    jQuery.post( ajaxurl, coinsnapData, function( response ){

        connectionCheckResponse = $.parseJSON(response);
        let resultClass = (connectionCheckResponse.result === true)? 'success' : 'error';
        $connectionCheckMessage = $('<div class="notice notice-'+resultClass+' is-dismissible '+resultClass+'"><p></p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button></div>');
        $connectionCheckMessage.find('p').text(connectionCheckResponse.message);

        if(connectionCheckElement !== ''){
            $(connectionCheckElement).prepend($connectionCheckMessage);
                $('.notice-dismiss').click(function(){
                    $(this).parent().hide(500); 
                });
        }
        
        if($('#coinsnapConnectionStatus').length){
            $('#coinsnapConnectionStatus').empty().append($('<span>').addClass(resultClass).text(connectionCheckResponse.message));
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

