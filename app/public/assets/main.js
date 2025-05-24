document.addEventListener('DOMContentLoaded', function () {
  const buttonSearch = document.querySelector('#btn-search');
  const buttonAdd = document.querySelector('#button-add');
  const token = document.querySelector('#token');

  buttonSearch.addEventListener('click', async function () {
    const telegramId = document.querySelector('#telegram-id')?.value?.trim() || null;

    if (!telegramId) {
      alert('Пожалуйста, введите Telegram ID.')

      return;
    }

    try {
      setDisplay('.not-found', false)
      setDisplay('.user-data', false)
      setDisplay('.action', false)

      const params = new URLSearchParams({
        telegram_id: telegramId,
      });

      const response = await fetch(`/api/home/data?${params.toString()}`, {
        method: 'GET',
        headers: {
          'token': token.value,
        },
      });

      if (response.status === 404) {
        setDisplay('.not-found', true)
      }

      if (response.status === 200) {
        response.json().then(data => {
          updateUserData(data);
        });
      }

      setDisplay('.action', true)
    } catch (error) {
    }

  });

  buttonAdd.addEventListener('click', async function () {
    const telegramId = document.querySelector('#telegram-id')?.value?.trim() || null;

    if (!telegramId) {
      return;
    }

    try {
      setDisplay('.not-found', false)
      setDisplay('.user-data', false)
      setDisplay('.action', false)

      const response = await fetch('/api/home/data/add', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'token': token.value,
        },
        body: JSON.stringify({
          telegram_id: telegramId,
          rate: document.querySelector('#rate').value,
        }),
      });

      if (response.status === 200) {
        alert('Подписка успешно добавлена или обновлена')
        buttonSearch.click();
      } else {
        alert('Ошибка!')
      }
    } catch (error) {
      alert('Ошибка!')
    }
  });

  function setDisplay(selector, show) {
    const elements = document.querySelectorAll(selector);

    elements.forEach(el => {
      el.style.display = show ? '' : 'none';
    });
  }

  function updateUserData(data) {
    const userDataBlock = document.querySelector('.user-data');
    userDataBlock.style.display = '';

    // Статичные поля
    userDataBlock.querySelector('.telegramId span').textContent = data.telegramId ?? '';
    userDataBlock.querySelector('.activeSubscription span').textContent = data.activeSubscription ? 'Да' : 'Нет';
    userDataBlock.querySelector('.countSubscription span').textContent = data.countSubscription ?? '0';
    userDataBlock.querySelector('.endSubscriptionDate span').textContent = data.endSubscriptionDate ?? '-';

    // Очистить блок с подписками
    const subscriptionsContainer = userDataBlock.querySelector('.subscriptions');
    subscriptionsContainer.innerHTML = '';

    // Добавить заголовок и подписки
    if (Array.isArray(data.currentSubscriptions) && data.currentSubscriptions.length > 0) {
      const title = document.createElement('p');
      title.textContent = 'Активные подписки:';
      subscriptionsContainer.appendChild(title);

      data.currentSubscriptions.forEach(sub => {
        const subDiv = document.createElement('div');
        subDiv.classList.add('subscription');

        subDiv.innerHTML = `
        <p class="type">Подписка: <span>${sub.type}</span></p>
        <p class="endDate">Дата подписки: <span>${sub.endDate}</span></p>
      `;

        subscriptionsContainer.appendChild(subDiv);
      });
    } else {
      const noSubs = document.createElement('p');
      noSubs.textContent = 'Нет активных подписок.';
      subscriptionsContainer.appendChild(noSubs);
    }
  }
});
