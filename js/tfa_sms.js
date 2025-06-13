(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.tfaSmsResend = {
    attach: function (context, settings) {
      // Инициализация таймера при загрузке страницы
      const resendCode = context.querySelector('.resend-code');
      if (resendCode) {
        const resendText = resendCode.querySelector('.resend-text');
        if (resendText) {
          const timeMatch = resendText.textContent.match(/\d+/);
          if (timeMatch) {
            let timeLeft = parseInt(timeMatch[0], 10);
            if (timeLeft > 0) {
              const timer = setInterval(function() {
                timeLeft--;
                if (timeLeft <= 0) {
                  clearInterval(timer);
                  resendCode.classList.remove('disabled');
                  resendCode.innerHTML = `<a href="#" class="resend-link">${Drupal.t('Resend code')}</a>`;
                  
                  // Добавляем обработчик клика после истечения таймера
                  once('tfa-sms-resend', '.resend-link', context).forEach(function (element) {
                    element.addEventListener('click', handleResendClick);
                  });
                } else {
                  resendText.textContent = Drupal.t('Resend code (@time_left s)', { '@time_left': timeLeft });
                }
              }, 1000);
            }
          }
        }
      }

      // Обработчик клика по ссылке
      function handleResendClick(e) {
        e.preventDefault();
        const link = this;
        link.classList.add('disabled');
        link.textContent = Drupal.t('Sending...');
        
        // Get the form
        const form = link.closest('form');
        if (!form) {
          return;
        }

        // Get user ID from the form
        const uid = form.querySelector('input[name="uid"]').value;
        if (!uid) {
          return;
        }

        // Send AJAX request to resend code endpoint
        fetch(Drupal.url('tfa/sms/resend/' + uid), {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
        })
        .then(response => response.json())
        .then(response => {
          console.log('AJAX response:', response);
          
          // Reset the timer
          let timeLeft = 30;
          const resendCode = document.querySelector('.resend-code');
          resendCode.classList.add('disabled');
          resendCode.innerHTML = `<span class="resend-text">${Drupal.t('Resend code (@time_left s)', { '@time_left': timeLeft })}</span>`;
          
          // Start timer
          const timer = setInterval(function() {
            timeLeft--;
            if (timeLeft <= 0) {
              clearInterval(timer);
              resendCode.classList.remove('disabled');
              resendCode.innerHTML = `<a href="#" class="resend-link">${Drupal.t('Resend code')}</a>`;
              
              // Reattach click handler
              once('tfa-sms-resend', '.resend-link', context).forEach(function (element) {
                element.addEventListener('click', handleResendClick);
              });
            } else {
              resendCode.querySelector('.resend-text').textContent = Drupal.t('Resend code (@time_left s)', { '@time_left': timeLeft });
            }
          }, 1000);
          
          // Show success message
          if (response.messages && response.messages.status) {
            const message = response.messages.status[0].message;
            const messageWrapper = document.createElement('div');
            messageWrapper.className = 'messages messages--status';
            messageWrapper.innerHTML = `<div class="messages__content">${message}</div>`;
            form.parentNode.insertBefore(messageWrapper, form);
            setTimeout(() => messageWrapper.style.display = 'none', 5000);
          }
        })
        .catch(error => {
          console.error('AJAX error:', error);
          link.classList.remove('disabled');
          link.textContent = Drupal.t('Resend code');
          const messageWrapper = document.createElement('div');
          messageWrapper.className = 'messages messages--error';
          messageWrapper.innerHTML = `<div class="messages__content">${Drupal.t('Failed to send verification code. Please try again later.')}</div>`;
          form.parentNode.insertBefore(messageWrapper, form);
          setTimeout(() => messageWrapper.style.display = 'none', 5000);
        });
      }

      // Добавляем обработчик клика для активных ссылок
      once('tfa-sms-resend', '.resend-link', context).forEach(function (element) {
        element.addEventListener('click', handleResendClick);
      });
    }
  };
})(Drupal, once); 