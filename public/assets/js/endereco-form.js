/**
 * ============================================================
 * MÓDULO: Formulário de Endereço com Consulta de CEP
 * ============================================================
 * 
 * Propósito:
 * Automatiza preenchimento de endereços via API ViaCEP.
 * Valida CEP e aplica máscaras de formatação.
 * 
 * Funcionalidades:
 * - Consulta automática de CEP (blur event)
 * - Preenchimento de rua, bairro, cidade, estado
 * - Máscara de formatação (00000-000)
 * - Feedback visual (sucesso/erro)
 * - Suporte a múltiplos formulários na mesma página
 * 
 * Estrutura HTML esperada:
 * <form data-endereco-form>
 *   <input data-cep-input name="cep">
 *   <input data-field="rua" name="rua" readonly>
 *   <input data-field="bairro" name="bairro" readonly>
 *   <input data-field="cidade" name="cidade" readonly>
 *   <input data-field="estado" name="estado" readonly>
 *   <div data-cep-feedback></div>
 * </form>
 * 
 * API Utilizada:
 * - ViaCEP: https://viacep.com.br/ws/{CEP}/json/
 * - Gratuita, sem autenticação
 * - Formato de retorno: JSON
 */

// ===== IIFE: ENCAPSULAMENTO DO CÓDIGO =====
/**
 * Immediately Invoked Function Expression (IIFE).
 * Previne poluição do escopo global e conflitos de variáveis.
 */
(function () {
  // ===== SELEÇÃO DOS FORMULÁRIOS =====
  /**
   * Busca todos os formulários com atributo [data-endereco-form].
   * Isso permite reutilizar o script em múltiplas páginas:
   * - addEnd.php (adicionar endereço)
   * - editarEnd.php (editar endereço)
   * - checkout.php (endereço de entrega)
   */
  const forms = document.querySelectorAll('[data-endereco-form]');
  
  // Guard clause: se não houver formulários, encerra script
  if (!forms.length) {
    return;
  }

  // ===== ITERAÇÃO: INICIALIZA CADA FORMULÁRIO =====
  /**
   * Loop sobre todos os formulários encontrados.
   * Cada um recebe seu próprio set de event listeners.
   */
  forms.forEach((form) => {
    // ===== SELEÇÃO DOS CAMPOS DO FORMULÁRIO =====
    /**
     * Busca campos específicos dentro do formulário atual.
     * Usa data-attributes para seleção semântica.
     */
    const cepInput = form.querySelector('[data-cep-input]');
    const ruaInput = form.querySelector('[data-field="rua"]');
    const bairroInput = form.querySelector('[data-field="bairro"]');
    const cidadeInput = form.querySelector('[data-field="cidade"]');
    const estadoInput = form.querySelector('[data-field="estado"]');
    const feedbackEl = form.querySelector('[data-cep-feedback]');

    /**
     * Validação: verifica se todos os campos obrigatórios existem.
     * Guard clause: pula este formulário se algum campo estiver faltando.
     */
    if (!cepInput || !ruaInput || !bairroInput || !cidadeInput || !estadoInput) {
      return;
    }

    // ===== FUNÇÃO: EXIBIR FEEDBACK VISUAL =====
    /**
     * Atualiza elemento de feedback com mensagem e estilo apropriado.
     * 
     * @param {string} message - Texto a exibir (vazio para limpar)
     * @param {boolean} isError - True para erro (vermelho), false para sucesso (verde)
     * 
     * Classes CSS aplicadas:
     * - .error: borda/texto vermelho
     * - .success: borda/texto verde
     * 
     * Acessibilidade:
     * - role="alert": notifica leitores de tela sobre mudanças
     */
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

    // ===== FUNÇÃO: MÁSCARA DE CEP =====
    /**
     * Formata string para padrão de CEP brasileiro (00000-000).
     * 
     * Processo:
     * 1. Remove todos os não-dígitos (\D)
     * 2. Limita a 8 dígitos (slice)
     * 3. Adiciona hífen após 5º dígito
     * 
     * Exemplos:
     * - "12345678" → "12345-678"
     * - "12345" → "12345"
     * - "abc12345xyz" → "12345"
     * 
     * @param {string} value - Valor bruto do input
     * @returns {string} - CEP formatado
     */
    const applyMask = (value) => {
      const digits = value.replace(/\D+/g, '').slice(0, 8);
      if (digits.length <= 5) {
        return digits;
      }
      return `${digits.slice(0, 5)}-${digits.slice(5)}`;
    };

    // ===== FUNÇÃO: PREENCHER CAMPOS DE ENDEREÇO =====
    /**
     * Popula campos readonly com dados da API ViaCEP.
     * 
     * Estrutura de resposta ViaCEP:
     * {
     *   "cep": "01310-100",
     *   "logradouro": "Avenida Paulista",
     *   "bairro": "Bela Vista",
     *   "localidade": "São Paulo",
     *   "uf": "SP"
     * }
     * 
     * Fallback: string vazia se campo não existir na resposta.
     * 
     * @param {Object} data - Objeto retornado pela API ViaCEP
     */
    const fillAddress = (data) => {
      ruaInput.value = data.logradouro || '';
      bairroInput.value = data.bairro || '';
      cidadeInput.value = data.localidade || '';
      estadoInput.value = data.uf || '';
    };

    // ===== FUNÇÃO: LIMPAR CAMPOS DE ENDEREÇO =====
    /**
     * Reseta todos os campos de endereço para vazio.
     * Usado em casos de erro ou CEP inválido.
     */
    const clearAddress = () => {
      ruaInput.value = '';
      bairroInput.value = '';
      cidadeInput.value = '';
      estadoInput.value = '';
    };

    // ===== FUNÇÃO: CONSULTAR CEP NA API =====
    /**
     * Busca dados do CEP na API ViaCEP (async).
     * 
     * Fluxo:
     * 1. Valida quantidade de dígitos (deve ser 8)
     * 2. Faz requisição GET para ViaCEP
     * 3. Parse do JSON de resposta
     * 4. Preenche campos ou exibe erro
     * 
     * Estados de feedback:
     * - "Consultando CEP..." (loading)
     * - "Endereço encontrado automaticamente." (sucesso)
     * - "CEP não encontrado." (erro 404)
     * - "Erro ao consultar o CEP." (erro de rede)
     * 
     * Endpoint: https://viacep.com.br/ws/{CEP}/json/
     */
    const lookupCep = async () => {
      // Remove formatação e valida
      const digits = cepInput.value.replace(/\D+/g, '');
      
      // CEP vazio: limpa campos sem erro
      if (digits.length === 0) {
        clearAddress();
        setFeedback('', false);
        return;
      }

      // CEP incompleto: mostra erro
      if (digits.length !== 8) {
        clearAddress();
        setFeedback('Digite um CEP válido com 8 dígitos.', true);
        return;
      }

      // Estado de loading
      setFeedback('Consultando CEP...', false);
      
      try {
        // Requisição à API ViaCEP
        const response = await fetch(`https://viacep.com.br/ws/${digits}/json/`);
        
        // Verifica status HTTP
        if (!response.ok) {
          throw new Error('Não foi possível consultar o CEP.');
        }
        
        // Parse da resposta JSON
        const data = await response.json();
        
        // ViaCEP retorna {erro: true} para CEPs inexistentes
        if (data.erro) {
          clearAddress();
          setFeedback('CEP não encontrado.', true);
          return;
        }
        
        // Sucesso: preenche campos
        fillAddress(data);
        setFeedback('Endereço encontrado automaticamente.', false);
      } catch (error) {
        // Erro de rede, timeout, etc
        clearAddress();
        setFeedback('Erro ao consultar o CEP. Tente novamente.', true);
      }
    };

    // ===== EVENT LISTENER: INPUT (MÁSCARA EM TEMPO REAL) =====
    /**
     * Aplica máscara enquanto usuário digita.
     * 
     * Evento 'input':
     * - Dispara a cada tecla pressionada
     * - Atualiza valor com máscara aplicada
     * - Limpa feedback se CEP incompleto
     */
    cepInput.addEventListener('input', (event) => {
      const masked = applyMask(event.target.value);
      event.target.value = masked;
      // Limpa feedback enquanto usuário ainda está digitando
      if (feedbackEl && masked.replace(/\D+/g, '').length < 8) {
        setFeedback('', false);
      }
    });

    // ===== EVENT LISTENER: BLUR (CONSULTA AUTOMÁTICA) =====
    /**
     * Dispara consulta quando usuário sai do campo (blur).
     * 
     * UX: consulta automática sem precisar clicar em botão.
     * Alternativa seria usar 'change' ou botão manual.
     */
    cepInput.addEventListener('blur', lookupCep);
  });
})();
