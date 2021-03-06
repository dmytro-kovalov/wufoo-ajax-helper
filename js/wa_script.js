;(function ($, document, window, wufooAjax) {
  'use strict';

  $(document).on('submit', '.wufoo-form', function(e) {
    e.preventDefault();
    const $form = $(this);
    const $responseContainer = $('<div />', {'class': 'validation-feedback'});
    let formType = $form.data('form-type');

    if (formType === undefined) {
      console.log('ERROR: Form type not defined. Include a data-form-type attribute on your form tag');
      return;
    }

    let isSubscribeForm = $form.hasClass('subscribe-mini') ||
                          $form.hasClass('subscribe-block');

    let formData = new FormData(this);
    formData.append('action', 'wufoo_post');
    formData.append('form_type', formType);

    $.ajax({
      type: 'POST',
      url: wufooAjax.ajaxurl,
      data: formData,
      dataType: 'json',
      cache: false,
      contentType: false,
      processData: false,
      beforeSend: function(xhr, settings) {
        // Remove all .input-error from fields as well as .error-message
        $form.find( 'input' ).removeClass( 'input-error' );
        $form.find( '.error-message' ).remove();
      },
      success: function(response) {
        console.log(response);
        if (response.Success !== 1) {
          // we have a field error, so loop through the errors
          let errors = response.FieldErrors;
          let errorsList = [];
          $.each(errors, function() {
            // in case of multiple errors let's log them with their fieldID's
            console.log(this.ID + ': ' + this.ErrorText);
            $form.find('#' + this.ID).addClass('input-error').after($('<span />', {'class': 'error-message', text: this.ErrorText}));
            if (!isSubscribeForm) {
              let placeholder = $form.find('#' + this.ID).attr('placeholder');
              errorsList.push(placeholder + ': ' + this.ErrorText);
            }
          });

          if (!isSubscribeForm && errorsList.length > 0) {
            $responseContainer.insertAfter($form);
            $responseContainer.addClass('feedback-error');
            $responseContainer.append( $('<p/>', {text: wufooAjax.strings.fixErrors}));
            $responseContainer.append( $('<ul/>') );
            $responseContainer.find('ul').html('<li>' + errorsList.join('</li><li>') + '</li>');
          }
        } else {
          $responseContainer.insertAfter($form);
          $responseContainer.addClass('feedback-success');
          $responseContainer.text(wufooAjax.strings.submitSuccess);
          $form.trigger('reset');
        }
      },
      error: function(jqXHR, textStatus, errorThrown) {
        // HTML error in communicating with Wufoo
        console.error(errorThrown);
      },
    });
  });

})(jQuery, document, window, wufooAjax);