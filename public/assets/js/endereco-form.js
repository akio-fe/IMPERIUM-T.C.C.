/**
 * ============================================================
 * MÓDULO: Preenchimento Automático de Endereço por CEP
 * ============================================================
 * 
 * Propósito:
 * Facilita cadastro de endereços com consulta automática via CEP.
 * Usa API ViaCEP (gratuita) para buscar dados de logradouro.
 * 
 * Funcionalidades:
 * - Máscara automática de CEP (00000-000)
 * - Consulta automática ao perder foco (blur)
 * - Preenchimento automático de campos (rua, bairro, cidade, estado)
 * - Feedback visual de carregamento e erros
 * - Suporte a múltiplos formulários na mesma página
 * - Acessibilidade (role="alert" para mensagens)
 * 
 * API Externa:
 * - ViaCEP (https://viacep.com.br)
 * - Endpoint: GET /ws/{cep}/json/
 * - Gratuita, sem necessidade de chave API
 * - Retorna: logradouro, bairro, localidade (cidade), uf (estado)
 * 
 * Arquitetura:
 * - IIFE (Immediately Invoked Function Expression)
 * - Encapsulamento de variáveis (evita poluir escopo global)
 * - Data attributes para seleção de elementos
 * - Event-driven (input e blur events)
 * 
 * HTML esperado:
 * <form data-endereco-form>
 *   <input data-cep-input type="text">
 *   <input data-field="rua" type="text">
 *   <input data-field="bairro" type="text">
 *   <input data-field="cidade" type="text">
 *   <input data-field="estado" type="text">
 *   <div data-cep-feedback></div>
 * </form>
 * 
 * Data Attributes:
 * - data-endereco-form: identifica formulários com funcionalidade de CEP
 * - data-cep-input: campo de entrada do CEP
 * - data-field="nome": campos a serem preenchidos automaticamente
 * - data-cep-feedback: elemento para mensagens de status
 */

// ===== IIFE: ENCAPSULAMENTO DO MÓDULO =====
/**
 * Immediately Invoked Function Expression (IIFE).
 * 
 * Benefícios:
 * - Cria escopo privado para variáveis
 * - Evita conflitos com outros scripts
 * - Executa imediatamente sem poluir global namespace
 * 
 * Padrão:
 * (function() { /* código */ })();
 * 
 * Execução:
 * - Função é definida e chamada imediatamente
 * - Variáveis internas não vazam para window
 */
(function () {
  // ===== SELEÇÃO DE FORMULÁRIOS =====
  /**
   * Busca todos os formulários com funcionalidade de CEP.
   * 
   * Seletor: [data-endereco-form]
   * - Permite múltiplos formulários na mesma página
   * - Ex: formulário de cadastro + formulário de edição
   * 
   * Early return:
   * - Se nenhum formulário encontrado, sai da função
   * - Previne erros em páginas sem formulários de endereço
   */
  const forms = document.querySelectorAll('[data-endereco-form]');
  if (!forms.length) {
    return;
  }

  // ===== ITERAÇÃO SOBRE FORMULÁRIOS =====
  /**
   * Processa cada formulário individualmente.
   * 
   * Benefício:
   * - Cada formulário tem seus próprios listeners
   * - Formulários funcionam independentemente
   * - Permite configurações diferentes por formulário
   */
  forms.forEach((form) => {
    // ===== REFERÊNCIAS DOS ELEMENTOS HTML =====
    /**
     * Busca elementos do formulário usando data attributes.
     * 
     * Campos esperados:
     * - cepInput: campo de entrada do CEP (8 dígitos)
     * - ruaInput: logradouro (preenchido automaticamente)
     * - bairroInput: bairro (preenchido automaticamente)
     * - cidadeInput: cidade (preenchido automaticamente)
     * - estadoInput: UF com 2 letras (preenchido automaticamente)
     * - feedbackEl: elemento para mensagens de status (opcional)
     * 
     * Validação:
     * - Se campos obrigatórios não existirem, pula este formulário
     * - Previne erros de referência nula
     */
    const cepInput = form.querySelector('[data-cep-input]');
    const ruaInput = form.querySelector('[data-field="rua"]');
    const bairroInput = form.querySelector('[data-field="bairro"]');
    const cidadeInput = form.querySelector('[data-field="cidade"]');
    const estadoInput = form.querySelector('[data-field="estado"]');
    const feedbackEl = form.querySelector('[data-cep-feedback]');

    if (!cepInput || !ruaInput || !bairroInput || !cidadeInput || !estadoInput) {
      return;
    }

    // ===== FUNÇÃO: EXIBIR FEEDBACK VISUAL =====
    /**
     * Atualiza elemento de feedback com mensagens e classes CSS.
     * 
     * Parâmetros:
     * - message: texto a exibir (vazio para limpar)
     * - isError: se true, aplica estilo de erro (vermelho)
     * 
     * Classes CSS:
     * - 'error': mensagem de erro (vermelho)
     * - 'success': mensagem de sucesso (verde)
     * 
     * Acessibilidade:
     * - role="alert": anuncia mensagens para leitores de tela
     * - Importante para usuários com deficiência visual
     * - Removido quando mensagem vazia (evita anúncios desnecessários)
     * 
     * @param {string} message - Texto da mensagem
     * @param {boolean} isError - Se é mensagem de erro
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

    // ===== FUNÇÃO: APLICAR MÁSCARA DE CEP =====
    /**
     * Formata entrada como CEP brasileiro (00000-000).
     * 
     * Lógica:
     * 1. Remove todos os caracteres não-numéricos
     * 2. Limita a 8 dígitos (tamanho máximo de CEP)
     * 3. Se até 5 dígitos: retorna sem formatação
     * 4. Se mais de 5: insere hífen após 5º dígito
     * 
     * Exemplos:
     * - "12345" → "12345"
     * - "123456" → "12345-6"
     * - "12345678" → "12345-678"
     * - "12345-678" → "12345-678" (já formatado)
     * - "abc12345678xyz" → "12345-678" (remove letras)
     * 
     * Uso:
     * - Chamado no evento 'input' (cada tecla digitada)
     * - Fornece feedback visual imediato ao usuário
     * 
     * @param {string} value - Valor atual do input
     * @returns {string} - Valor formatado
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
     * Popula campos do formulário com dados da API ViaCEP.
     * 
     * Mapeamento:
     * - data.logradouro → campo rua
     * - data.bairro → campo bairro
     * - data.localidade → campo cidade
     * - data.uf → campo estado
     * 
     * Fallback:
     * - Usa string vazia se campo não retornado pela API
     * - Alguns CEPs não têm logradouro (áreas rurais)
     * - Usuário pode preencher manualmente campos vazios
     * 
     * @param {Object} data - Dados retornados pela API ViaCEP
     */
    const fillAddress = (data) => {
      ruaInput.value = data.logradouro || '';
      bairroInput.value = data.bairro || '';
      cidadeInput.value = data.localidade || '';
      estadoInput.value = data.uf || '';
    };

    // ===== FUNÇÃO: LIMPAR CAMPOS DE ENDEREÇO =====
    /**
     * Reseta campos de endereço para estado vazio.
     * 
     * Uso:
     * - CEP inválido ou não encontrado
     * - Usuário apaga CEP completamente
     * - Previne dados antigos de consultas anteriores
     * 
     * Comportamento:
     * - Mantém CEP (usuário pode estar editando)
     * - Limpa apenas campos dependentes (rua, bairro, cidade, estado)
     */
    const clearAddress = () => {
      ruaInput.value = '';
      bairroInput.value = '';
      cidadeInput.value = '';
      estadoInput.value = '';
    };

    // ===== FUNÇÃO: CONSULTAR CEP NA API VIACEP =====
    /**
     * Busca dados de endereço baseado no CEP informado.
     * 
     * Validações:
     * 1. CEP vazio: limpa campos e feedback (reset)
     * 2. CEP com menos de 8 dígitos: exibe erro de validação
     * 3. CEP com 8 dígitos: faz requisição à API
     * 
     * Fluxo de Requisição:
     * 1. Exibe "Consultando CEP..."
     * 2. Faz fetch para ViaCEP
     * 3. Verifica se resposta HTTP é OK (200-299)
     * 4. Converte resposta para JSON
     * 5. Verifica campo 'erro' no JSON (CEP não encontrado)
     * 6. Preenche campos com dados retornados
     * 7. Exibe mensagem de sucesso
     * 
     * Tratamento de Erros:
     * - Rede indisponível (catch)
     * - CEP não encontrado (data.erro)
     * - Resposta HTTP não OK (!response.ok)
     * 
     * API ViaCEP:
     * - Endpoint: https://viacep.com.br/ws/{cep}/json/
     * - Formato: JSON
     * - Campos: cep, logradouro, complemento, bairro, localidade, uf, ibge, gia, ddd, siafi
     * - Campo 'erro': true se CEP não existir
     * 
     * Async/Await:
     * - Função assíncrona para usar await
     * - Evita callback hell
     * - Código mais legível e linear
     */
    const lookupCep = async () => {
      // Remove formatação e obtém apenas dígitos
      const digits = cepInput.value.replace(/\D+/g, '');
      
      // ===== VALIDAÇÃO: CEP VAZIO =====
      if (digits.length === 0) {
        clearAddress();
        setFeedback('', false);
        return;
      }

      // ===== VALIDAÇÃO: CEP INCOMPLETO =====
      if (digits.length !== 8) {
        clearAddress();
        setFeedback('Digite um CEP válido com 8 dígitos.', true);
        return;
      }

      // ===== FEEDBACK: CONSULTANDO =====
      setFeedback('Consultando CEP...', false);
      
      try {
        // ===== REQUISIÇÃO À API VIACEP =====
        /**
         * Fetch API para buscar dados do CEP.
         * 
         * URL: https://viacep.com.br/ws/12345678/json/
         * - {digits}: CEP sem formatação (apenas números)
         * 
         * Método: GET (padrão do fetch)
         * Headers: nenhum necessário
         * 
         * Resposta de sucesso:
         * {
         *   "cep": "01001-000",
         *   "logradouro": "Praça da Sé",
         *   "complemento": "lado ímpar",
         *   "bairro": "Sé",
         *   "localidade": "São Paulo",
         *   "uf": "SP",
         *   "ibge": "3550308",
         *   "gia": "1004",
         *   "ddd": "11",
         *   "siafi": "7107"
         * }
         * 
         * Resposta de erro (CEP não encontrado):
         * {
         *   "erro": true
         * }
         */
        const response = await fetch(`https://viacep.com.br/ws/${digits}/json/`);
        
        if (!response.ok) {
          throw new Error('Não foi possível consultar o CEP.');
        }
        
        const data = await response.json();
        
        // ===== TRATAMENTO: CEP NÃO ENCONTRADO =====
        /**
         * API retorna { erro: true } se CEP não existir.
         * 
         * Cenários:
         * - CEP numericamente válido mas não cadastrado nos Correios
         * - CEP de região não mapeada
         * 
         * Ação:
         * - Limpa campos preenchidos anteriormente
         * - Exibe mensagem de erro amigável
         */
        if (data.erro) {
          clearAddress();
          setFeedback('CEP não encontrado.', true);
          return;
        }
        
        // ===== SUCESSO: PREENCHER CAMPOS =====
        fillAddress(data);
        setFeedback('Endereço encontrado automaticamente.', false);
        
      } catch (error) {
        // ===== TRATAMENTO DE ERROS DE REDE =====
        /**
         * Captura erros de:
         * - Conexão à internet
         * - Timeout da requisição
         * - Resposta não-JSON
         * - CORS (improvável com ViaCEP)
         * 
         * Ação:
         * - Limpa campos para evitar dados inconsistentes
         * - Exibe mensagem de erro genérica
         * - Loga erro no console para debug
         */
        clearAddress();
        setFeedback('Erro ao consultar o CEP. Tente novamente.', true);
      }
    };

    // ===== EVENTO: INPUT (DIGITAÇÃO) =====
    /**
     * Listener executado a cada tecla digitada no campo CEP.
     * 
     * Responsabilidades:
     * 1. Aplicar máscara de formatação (00000-000)
     * 2. Limpar feedback se CEP incompleto
     * 
     * Fluxo:
     * - Usuário digita "1" → valor fica "1"
     * - Usuário digita "12345" → valor fica "12345"
     * - Usuário digita "6" → valor fica "12345-6"
     * - Usuário digita "78" → valor fica "12345-678"
     * 
     * UX:
     * - Formatação acontece em tempo real
     * - Usuário não precisa digitar hífen manualmente
     * - Feedback limpo enquanto digita (não distrai)
     */
    cepInput.addEventListener('input', (event) => {
      const masked = applyMask(event.target.value);
      event.target.value = masked;
      // Limpa feedback se ainda não completou 8 dígitos
      if (feedbackEl && masked.replace(/\D+/g, '').length < 8) {
        setFeedback('', false);
      }
    });

    // ===== EVENTO: BLUR (PERDER FOCO) =====
    /**
     * Listener executado quando usuário sai do campo CEP.
     * 
     * Comportamento:
     * - Usuário digita CEP completo
     * - Usuário clica fora do campo ou pressiona Tab
     * - Consulta automática é disparada
     * 
     * Vantagem:
     * - Não consulta a cada tecla (economiza requisições)
     * - Consulta apenas quando CEP completo
     * - UX fluida: preenche campos automaticamente após digitação
     * 
     * Alternativa não usada:
     * - Botão "Buscar CEP" (requer ação extra do usuário)
     * - Consulta no submit (tardio, pode causar frustração)
     */
    cepInput.addEventListener('blur', lookupCep);
  });
})();
