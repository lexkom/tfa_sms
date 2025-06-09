(function ($, Drupal, once) {
  'use strict';

  Drupal.behaviors.tfaSmsResend = {
    attach: function (context, settings) {
      // Инициализация таймера при загрузке страницы
      const $resendCode = $('.resend-code', context);
      if ($resendCode.length > 0) {
        const $resendText = $resendCode.find('.resend-text');
        if ($resendText.length > 0) {
          const timeMatch = $resendText.text().match(/\d+/);
          if (timeMatch) {
            let timeLeft = parseInt(timeMatch[0], 10);
            if (timeLeft > 0) {
              const timer = setInterval(function() {
                timeLeft--;
                if (timeLeft <= 0) {
                  clearInterval(timer);
                  $resendCode.removeClass('disabled');
                  $resendCode.html('<a href="#" class="resend-link">' + Drupal.t('Resend code') + '</a>');
                  
                  // Добавляем обработчик клика после истечения таймера
                  once('tfa-sms-resend', '.resend-link', context).forEach(function (element) {
                    element.addEventListener('click', handleResendClick);
                  });
                } else {
                  $resendText.text(Drupal.t('Resend code (@time_left s)', { '@time_left': timeLeft }));
                }
              }, 1000);
            }
          }
        }
      }

      // Обработчик клика по ссылке
      function handleResendClick(e) {
        e.preventDefault();
        const $link = $(this);
        $link.addClass('disabled').text(Drupal.t('Sending...'));
        
        // Get the form
        const $form = $link.closest('form');
        if (!$form.length) {
          return;
        }

        // Get user ID from the form
        const uid = $form.find('input[name="uid"]').val();
        if (!uid) {
          return;
        }

        // Send AJAX request to resend code endpoint
        $.ajax({
          url: Drupal.url('tfa/sms/resend/' + uid),
          type: 'POST',
          dataType: 'json',
          beforeSend: function() {
            console.log('Sending AJAX request to:', Drupal.url('tfa/sms/resend/' + uid));
          },
          success: function(response) {
            console.log('AJAX response:', response);
            
            // Reset the timer
            let timeLeft = 30;
            const $resendCode = $('.resend-code');
            $resendCode.addClass('disabled');
            $resendCode.html('<span class="resend-text">' + Drupal.t('Resend code (@time_left s)', { '@time_left': timeLeft }) + '</span>');
            
            // Start timer
            const timer = setInterval(function() {
              timeLeft--;
              if (timeLeft <= 0) {
                clearInterval(timer);
                $resendCode.removeClass('disabled');
                $resendCode.html('<a href="#" class="resend-link">' + Drupal.t('Resend code') + '</a>');
                
                // Reattach click handler
                once('tfa-sms-resend', '.resend-link', context).forEach(function (element) {
                  element.addEventListener('click', handleResendClick);
                });
              } else {
                $resendCode.find('.resend-text').text(Drupal.t('Resend code (@time_left s)', { '@time_left': timeLeft }));
              }
            }, 1000);
            
            // Show success message
            if (response.messages && response.messages.status) {
              const message = response.messages.status[0].message;
              const $messageWrapper = $('<div class="messages messages--status"><div class="messages__content">' + message + '</div></div>');
              $form.before($messageWrapper);
              setTimeout(() => $messageWrapper.fadeOut(), 5000);
            }
          },
          error: function(xhr, status, error) {
            console.error('AJAX error:', {xhr: xhr, status: status, error: error});
            $link.removeClass('disabled').text(Drupal.t('Resend code'));
            const $messageWrapper = $('<div class="messages messages--error"><div class="messages__content">' + Drupal.t('Failed to send verification code. Please try again later.') + '</div></div>');
            $form.before($messageWrapper);
            setTimeout(() => $messageWrapper.fadeOut(), 5000);
          }
        });
      }

      // Добавляем обработчик клика для активных ссылок
      once('tfa-sms-resend', '.resend-link', context).forEach(function (element) {
        element.addEventListener('click', handleResendClick);
      });
    }
  };
})(jQuery, Drupal, once); 