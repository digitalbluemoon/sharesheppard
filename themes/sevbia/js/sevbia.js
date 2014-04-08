(function ($, Drupal, window, document, undefined) {
$(document).ready(function() {
  $('#views-exposed-form-business-page-1 #edit-title').val('By Business');
  //$('#views-exposed-form-business-page-1 #fieldEmail').val("By Category")
  $('#views-exposed-form-business-page-1 #edit-title').click(function(){ 
								 
	if($('#views-exposed-form-business-page-1 #edit-title').val()=="By Business")	{
	  $('#views-exposed-form-business-page-1 #edit-title').val('');	
	}
						
  });
  $('#views-exposed-form-business-page-1 #edit-title').blur(function(){ 
    if($('#views-exposed-form-business-page-1 #edit-title').val()=="")	{
	  $('#views-exposed-form-business-page-1 #edit-title').val("By Business");	
	}
  });
  

  $('#views-exposed-form-directory-page-1 #edit-title').val('By Business');
  //$('#views-exposed-form-business-page-1 #fieldEmail').val("By Category")
  $('#views-exposed-form-directory-page-1 #edit-title').click(function(){ 
								 
	if($('#views-exposed-form-directory-page-1 #edit-title').val()=="By Business")	{
	  $('#views-exposed-form-directory-page-1 #edit-title').val('');	
	}
						
  });
  $('#views-exposed-form-directory-page-1 #edit-title').blur(function(){ 
    if($('#views-exposed-form-directory-page-1 #edit-title').val()=="")	{
	  $('#views-exposed-form-directory-page-1 #edit-title').val("By Business");	
	}
  });
  
  $('#views-exposed-form-directory-page-1 #edit-submit-directory').click(function(){ 
	if($('#views-exposed-form-directory-page-1 #edit-title').val()=="By Business")	{
	  $('#views-exposed-form-directory-page-1 #edit-title').val('');	
	}
  });

  var sidebar = $('#sidebar-first');
  if(sidebar.find('form').length)
    sidebar.removeClass('hidden-xs');
});						   
})(jQuery, Drupal, this, this.document);
