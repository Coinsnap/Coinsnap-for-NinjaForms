var nfCoinsnapDebugController = Marionette.Object.extend({

    initialize: function() {
        console.log( 'INIT' );
        // nfRadio.channel( 'submit' ).trigger( 'submit:response', response, textStatus, jqXHR );
        this.listenTo( Backbone.Radio.channel( 'submit' ), 'submit:response', this.response );
    },

    response: function( response, textStatus, jqXHR ) {
        if( 'undefined' == typeof response.data.actions.coinsnap ) return;
        if( 'undefined' == typeof response.data.actions.coinsnap.debug ) return;
        jQuery( '.nf-response-msg' ).after( this.formatData( response.data.actions.coinsnap.debug ) );
    },

    formatData: function( data ) {
        // TODO: Move to Underscore Template.
        // TODO: Translate static text.
        var output = '<table><tr><th colspan="2" style="text-align: center">Coinsnap Debug</th></tr>';
        _.each( data, function( value, property ) {
            output += '<tr><th>' + property + '</th><td>' + value + '</td></tr>';
        });
        output += '</table>';
        return '<div>' + output + '</div>';
    }

});

jQuery( document ).ready( function( $ ) {
    new nfCoinsnapDebugController();
});
