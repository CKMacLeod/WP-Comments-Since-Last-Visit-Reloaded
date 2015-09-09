/*
 * jQuery scripts for "Comments Since Last Visit Reloaded"
 * 
 * Author:  CK MacLeod
 * Date:    September 3, 2015
 * Updated: September 8, 2015
 * 
 */


//toggle the comment thread, and append a list in its place of new comments only

function cslvr_only() {
        
    //main toggle hiding all elements named except for #cslvr-comments-heading and sort button
    //which goes from display: none to visible
    
    //number new comments should be separate function (repeated below)
    var num_new_comments = jQuery('.new-comment').size();
     
    
    if (num_new_comments === 1) {
        
        var commenttext = ' comment';
        
    } else {
        
        var commenttext = ' comments';
    }
    
    var getting = '...getting ';
    var gotten = ' gotten';
    var showmsgs = jQuery('#show-only-messages');
        
    showmsgs.prepend(getting,num_new_comments,commenttext).fadeIn('fast', function() {
        
        showmsgs.text(function() {
            
            return num_new_comments + commenttext + gotten;
        
        }).delay(500).addClass('comments-gotten').fadeOut(2000);
    
    });
    
    jQuery('.comment,#respond,.new-comment,#go-to-next-top-button,#cslvr-sort-button,#cslvr-comments-heading').toggle('slow');
    
    //adjust button text and action depending on toggle state flagged by text of primary button
    
    var togglebutton = jQuery('#show-hide-cslvr-button');
            
    if (togglebutton.text() === 'Show New Comments Only') {
        
        //change text and clone and append new comments
        
        var newcomments = jQuery('.new-comment > article').clone();
        
        //may very possibly need to add formatting here to produce desired look
        //via jquery would look like: 
        newcomments.find('a.comment_reply_link,a.comment_quote_link').remove().end().appendTo('#cslvr-comments-heading').css({'height': 'auto', 'width': 'auto','margin': '5%','padding' : '5%'}).addClass('appended');
        
        //jQuery(newcomments).appendTo('#cslvr-comments-heading').addClass('appended');
        
        togglebutton.text('Show All Comments');
        
        //enable internal links in cloned comments, when clicked, to open page where target, 
        //hidden or not, appears in context 

        jQuery('.appended a').click(function(e) {
            
            e.preventDefault();

            var url = jQuery(this).attr('href');

            window.open(url);
                        
        });	
        
    } else {
        
        //since is "show all," if there are new comments appended, remove them
        
        togglebutton.text('Show New Comments Only');
        
        showmsgs.remove();
        
        jQuery('.appended').remove();
        
    }
} 




//enables looping "go to next clicker"
    
jQuery(document).ready(function() {
    
    var num_new_comments = jQuery('.new-comment').size();
    
    if (num_new_comments === 0) {
        
    jQuery('#cslvr-buttons,#cslvr-mark-all-read').addClass('no-new-comments');
    
    } else {
        
    if (num_new_comments === 1) {
        
        var new_commenttext = ' new comment';
        
        } else {
        
        var new_commenttext = ' new comments';
        
        }
        
    jQuery('#go-to-next-messages').text(function() {
            return num_new_comments + new_commenttext;
        }).addClass('new-comment-text').fadeIn('slow');   

    jQuery('.gtn_clicker').click(function() {
   
        var first_target;
       
        var target;
       
        //if at first "target" skip to next one on click

        jQuery('.new-comment').each(function(i, element) {
            
            if (i === 0) {
                
                //get position of first new comment
               
                first_target = jQuery(this).offset().top;
               
                //go to next instead of merely re-positioning over first
                
                return true;
                
           }
           
        //don't do anything if target isn't distant: note, the number 10 may be
        //be adjusted for some contexts   
           
        target = jQuery(element).offset().top;

        if (target - 10 > jQuery(document).scrollTop()) {
         
            return false; // break
         
        }
        
    });
       
     //if there is a worthwhile target, then hit it!

        if (target - 10 > jQuery(document).scrollTop()) {
        
            //go to next if available
            
            jQuery("html, body").animate({

                scrollTop: target

            }, 300);

        } else {
            
            //if at end return to first new comment
            //a little slower so user can see what's happening
         
            jQuery("html, body").animate({
                
                scrollTop: first_target
                
            }, 700);

        }

    });
    }
    
});   



//enables top "go to new comments button"

function cslvr_next() {
    
     var target;

    jQuery('.new-comment').each(function(i, element) {
           
        target = jQuery(element).offset().top;

        if (target - 10 > jQuery(document).scrollTop()) {

           return false; // break

        }

   });

    if (target - 10 > jQuery(document).scrollTop()) {

        jQuery("html, body").animate({

            scrollTop: target

        }, 300);
       
    }

}

//chronologically sort new new comment list on button click

function cslvr_sort() {

    if (jQuery('#cslvr-sort-button').text() === 'Sort Newest First') {
        
        var sortcomments = jQuery('.appended').sort( function(a,b) { 
            if(a.id < b.id ) {
                return 1;
            } else if(a.id > b.id ) {
                return -1;
            } else {
                return 0;
            }
        }).clone();
        
        var sorttext = 'Sort Oldest First';           
        
    } else {
        
        var sortcomments = jQuery('.appended').sort( function(a,b) { 
            if(a.id > b.id ) {
                return 1;
            } else if(a.id < b.id ) {
                return -1;
            } else {
                return 0;
            }
        }).clone();
        
        var sorttext = 'Sort Newest First';
        
    }
        
        jQuery('.appended').detach();
        
        jQuery(sortcomments).appendTo('#cslvr-comments-heading').addClass('sorted');
        
        //the "appended" class loses functionality even in the re-cloned set, so use
        //new "sorted" class to enable desired click functioning of internal links
        jQuery('.sorted a').click(function(e) {
            
            e.preventDefault();

            var url = jQuery(this).attr('href');

            window.open(url);
                        
        });	
        
       jQuery('#cslvr-sort-button').text(sorttext); 
       jQuery('#cslvr-sorted-messages').html('&nbsp;<b>sorted</b>').fadeIn(1000).fadeOut(1000);
       
      
    ;}