/**
 * Created by ldumorti on 22/04/16.
 */
/* jshint ignore:start */
define(['jquery'], function($) {
    var t = {
        /*init : function () {
            $(".groupmembers").hide();
        },*/
        display_results : function(){
            $('.memberlist').hover(function(){
                var groupid = this.id;
                var zoneName = '#groupmembers' + groupid;
                $(zoneName).toggle();
            });
            $('.display_details').hover(function(){
                var detailid = this.id.substring(4);
                var zoneName = '#detail' + detailid;
                $(zoneName).toogle()
            });
        },
    };
    return t;
});
/* jshint ignore:end */