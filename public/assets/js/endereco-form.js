(function () {
  const forms = document.querySelectorAll('[data-endereco-form]');
  if (!forms.length) {
    return;
  }

  forms.forEach((form) => {
    const cepInput = form.querySelector('[data-cep-input]');
    const ruaInput = form.querySelector('[data-field="rua"]');
    const bairroInput = form.querySelector('[data-field="bairro"]');
    const cidadeInput = form.querySelector('[data-field="cidade"]');
    const estadoInput = form.querySelector('[data-field="estado"]');
    const feedbackEl = form.querySelector('[data-cep-feedback]');

    if (!cepInput || !ruaInput || !bairroInput || !cidadeInput || !estadoInput) {
      return;
    }

    const setFeedback = (message = '', isError = false) => {
      if (!feedbackEl) {
        return;
      }
      feedbackEl.textContent = message;
      feedbackEl.classList.toggle('error', isError && message !== '');
      feedbackEl.classList.toggle('success', !isError && message !== '');
      if (message === '') {
        feedbackEl.removeAttribute('role');
      } else {
        feedbackEl.setAttribute('role', 'alert');
      }
    };

    const applyMask = (value) => {
      const digits = value.replace(/\D+/g, '').slice(0, 8);
      if (digits.length <= 5) {
        return digits;
      }
      return `${digits.slice(0, 5)}-${digits.slice(5)}`;
    };

    const fillAddress = (data) => {
      ruaInput.value = data.logradouro || '';
      bairroInput.value = data.bairro || '';
      cidadeInput.value = data.localidade || '';
      estadoInput.value = data.uf || '';
    };

    const clearAddress = () => {
      ruaInput.value = '';
      bairroInput.value = '';
      cidadeInput.value = '';
      estadoInput.value = '';
    };

    const lookupCep = async () => {
      const digits = cepInput.value.replace(/\D+/g, '');
      if (digits.length === 0) {
        clearAddress();
        setFeedback('', false);
        return;
      }

      if (digits.length !== 8) {
        clearAddress();
        setFeedback('Digite um CEP válido com 8 dígitos.', true);
        return;
      }

      setFeedback('Consultando CEP...', false);
      try {
        const response = await fetch(`https://viacep.com.br/ws/${digits}/json/`);
        if (!response.ok) {
          throw new Error('Não foi possível consultar o CEP.');
        }
        const data = await response.json();
        if (data.erro) {
          clearAddress();
          setFeedback('CEP não encontrado.', true);
          return;
        }
        fillAddress(data);
        setFeedback('Endereço encontrado automaticamente.', false);
      } catch (error) {
        clearAddress();
        setFeedback('Erro ao consultar o CEP. Tente novamente.', true);
      }
    };

    cepInput.addEventListener('input', (event) => {
      const masked = applyMask(event.target.value);
      event.target.value = masked;
      if (feedbackEl && masked.replace(/\D+/g, '').length < 8) {
        setFeedback('', false);
      }
    });

    cepInput.addEventListener('blur', lookupCep);
  });
})();
