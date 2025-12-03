/**
 * ============================================================
 * MÓDULO: Reemissão de Pagamento de Pedidos
 * ============================================================
 * 
 * Propósito:
 * Permite que usuário tente pagar novamente pedido com status "Aguardando Pagamento".
 * Integra com Mercado Pago para gerar novo link de checkout.
 * 
 * Funcionalidades:
 * - Botão "Ir para pagamento" em pedidos pendentes
 * - Requisição AJAX para gerar link do Mercado Pago
 * - Redirecionamento automático para checkout
 * - Feedback visual (loading state)
 * - Tratamento de erros
 * 
 * Fluxo:
 * 1. Usuário clica "Ir para pagamento" em pedido
 * 2. JavaScript envia POST para /api/checkout/reemitir_pagamento.php
 * 3. Backend gera nova preferência do Mercado Pago
 * 4. Retorna URL de pagamento
 * 5. Redireciona usuário para checkout do Mercado Pago
 * 
 * Estrutura HTML esperada:
 * <button class="js-pay-pedido" data-pay-pedido="123">Ir para pagamento</button>
 * 
 * Variável PHP injetada:
 * <?= $reemitirPagamentoUrl ?> = "/api/checkout/reemitir_pagamento.php"
 */

// ===== IIFE: ENCAPSULAMENTO =====

/**
 * Immediately Invoked Function Expression.
 * Previne poluição do escopo global.
 */
(function () {
  // ===== CONFIGURAÇÕES =====
  
  /**
   * Endpoint da API de reemissão de pagamento.
   * Injetado pelo PHP via template literal.
   * 
   * Nota: Em produção, deve usar variável JavaScript real,
   * não template literal PHP não processado.
   */
  const endpoint = "<?= $reemitirPagamentoUrl ?>";
  
  /**
   * Busca todos os botões de pagamento na página.
   * Seletor: .js-pay-pedido (prefixo "js-" indica uso exclusivo JavaScript).
   */
  const payButtons = document.querySelectorAll(".js-pay-pedido");
  
  // Guard clauses: encerra se não houver botões ou endpoint
  if (!payButtons.length || !endpoint) {
    return;
  }

  // ===== FUNÇÃO: SOLICITAR LINK DE PAGAMENTO =====
  
  /**
   * Faz requisição para API de reemissão de pagamento.
   * 
   * Processo:
   * 1. POST para endpoint com pedidoId
   * 2. Backend consulta pedido no banco
   * 3. Cria nova preferência no Mercado Pago
   * 4. Retorna URL de checkout
   * 
   * @param {number} pedidoId - ID do pedido (PedId)
   * @returns {Promise<string>} - URL do checkout Mercado Pago
   * @throws {Error} - Se API retornar erro ou resposta inválida
   */
  const solicitarPagamento = async (pedidoId) => {
    // ===== REQUISIÇÃO FETCH =====
    
    /**
     * POST assíncrono para endpoint.
     * 
     * Headers:
     * - Content-Type: JSON (body é serializado)
     * - Accept: esperamos JSON de resposta
     * 
     * Body:
     * { "pedidoId": 123 }
     */
    const resposta = await fetch(endpoint, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
      },
      body: JSON.stringify({
        pedidoId,
      }),
    });

    // ===== PARSE DA RESPOSTA =====
    
    /**
     * Tenta fazer parse do JSON.
     * catch(() => null): retorna null se parse falhar (resposta HTML de erro).
     */
    const payload = await resposta.json().catch(() => null);
    
    // ===== VALIDAÇÃO DA RESPOSTA =====
    
    /**
     * Verifica:
     * - resposta.ok: status HTTP 200-299
     * - payload existe (parse bem-sucedido)
     */
    if (!resposta.ok || !payload) {
      const mensagem =
        payload && payload.mensagem
          ? payload.mensagem
          : "Falha ao gerar o link de pagamento.";
      throw new Error(mensagem);
    }

    /**
     * Valida presença da URL de pagamento.
     * Backend deve retornar: { "pagamentoUrl": "https://..." }
     */
    if (!payload.pagamentoUrl) {
      throw new Error("Retorno inválido do serviço de pagamento.");
    }

    // Retorna URL para redirecionamento
    return payload.pagamentoUrl;
  };

  // ===== EVENT LISTENERS: BOTÕES DE PAGAMENTO =====
  
  /**
   * Itera sobre todos os botões de pagamento encontrados.
   * Cada botão recebe seu próprio listener.
   */
  payButtons.forEach((botao) => {
    /**
     * Handler do clique no botão "Ir para pagamento".
     * Função assíncrona para aguardar API.
     */
    botao.addEventListener("click", async () => {
      // ===== PROTEÇÃO CONTRA DUPLO CLIQUE =====
      
      /**
       * Previne múltiplas requisições simultâneas.
       * Botão desabilitado durante processamento.
       */
      if (botao.disabled) {
        return;
      }

      // ===== EXTRAÇÃO DO ID DO PEDIDO =====
      
      /**
       * Lê ID do pedido do atributo data-pay-pedido.
       * HTML: <button data-pay-pedido="123">...</button>
       * 
       * Converte para número (Number()) para validação.
       */
      const pedidoId = Number(botao.dataset.payPedido || 0);
      
      // Valida se ID é válido (> 0)
      if (!pedidoId) {
        alert("Pedido inválido.");
        return;
      }

      // ===== FEEDBACK VISUAL: LOADING STATE =====
      
      /**
       * Salva texto original para restaurar em caso de erro.
       * Desabilita botão e muda texto para "Gerando pagamento...".
       */
      const textoOriginal = botao.textContent;
      botao.disabled = true;
      botao.textContent = "Gerando pagamento...";

      try {
        // ===== SOLICITAÇÃO DE PAGAMENTO =====
        
        /**
         * Chama função que faz requisição à API.
         * Aguarda retorno (await) antes de prosseguir.
         */
        const pagamentoUrl = await solicitarPagamento(pedidoId);
        
        // ===== REDIRECIONAMENTO =====
        
        /**
         * Redireciona usuário para checkout do Mercado Pago.
         * URL retornada contém preferência já configurada.
         */
        window.location.href = pagamentoUrl;
      } catch (erro) {
        // ===== TRATAMENTO DE ERRO =====
        
        /**
         * Exibe mensagem de erro ao usuário.
         * Restaura estado original do botão (reabilita).
         */
        alert(
          erro.message || "Não foi possível redirecionar para o pagamento."
        );
        botao.disabled = false;
        botao.textContent = textoOriginal;
      }
    });
  });
})();
