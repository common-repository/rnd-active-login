jQuery(document).ready(function() {
    
    setInterval(function () {
        jQuery.ajax({
            url : ajax_object.ajax_url,
            type: 'POST',
            async: false,
            dataType: 'json',
            data: {
                action : 'getCurrentLoginStatus'
            },
            beforeSend : function(){
                
            },
            success : function( response ) {
                jQuery("#update-info").html('');
                
            }
        }); 
    }, ajax_object.guest_interval );

    setInterval(function () {
        jQuery.ajax({
            url : ajax_object.ajax_url,
            type: 'POST',
            async: false,
            dataType: 'json',
            data: {
                action : 'checkUserStatus'
            },
            beforeSend : function(){
                jQuery("#history-spiner-1").css('display', 'inline-block');
            },
            complete : function(){
                jQuery("#history-spiner-1").css('display','none');
            },
            success : function( response ) {
                jQuery("#user-login-detail").html(response);
            }
        }); 
    }, ajax_object.admin_interval ); 

    jQuery(document).on( 'click', '.view-login-history', function(){
        var _this = jQuery(this);
        jQuery.ajax({
            url : ajax_object.ajax_url,
            type: 'POST',
            async: false,
            dataType: 'json',
            data: {
                action : 'viewHistory',
                user_id : _this.attr('attr-id')
            },
            beforeSend : function(){
                jQuery("#history-spiner").css({
                    display: 'inline-block'
                });
            },
            complete : function(){
                jQuery("#history-spiner").css('display','none');
            },
            success : function( response ) {
                jQuery("#history-board").html('<h2>History</h2>'+response);
                
            }
        });

    } );
           

});  