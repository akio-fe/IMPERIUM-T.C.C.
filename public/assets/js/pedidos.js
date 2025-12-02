(function () {
  const endpoint = "<?= $reemitirPagamentoUrl ?>";
  const payButtons = document.querySelectorAll(".js-pay-pedido");
  if (!payButtons.length || !endpoint) {
    return;
  }

  const solicitarPagamento = async (pedidoId) => {
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

    const payload = await resposta.json().catch(() => null);
    if (!resposta.ok || !payload) {
      const mensagem =
        payload && payload.mensagem
          ? payload.mensagem
          : "Falha ao gerar o link de pagamento.";
      throw new Error(mensagem);
    }

    if (!payload.pagamentoUrl) {
      throw new Error("Retorno inválido do serviço de pagamento.");
    }

    return payload.pagamentoUrl;
  };

  payButtons.forEach((botao) => {
    botao.addEventListener("click", async () => {
      if (botao.disabled) {
        return;
      }

      const pedidoId = Number(botao.dataset.payPedido || 0);
      if (!pedidoId) {
        alert("Pedido inválido.");
        return;
      }

      const textoOriginal = botao.textContent;
      botao.disabled = true;
      botao.textContent = "Gerando pagamento...";

      try {
        const pagamentoUrl = await solicitarPagamento(pedidoId);
        window.location.href = pagamentoUrl;
      } catch (erro) {
        alert(
          erro.message || "Não foi possível redirecionar para o pagamento."
        );
        botao.disabled = false;
        botao.textContent = textoOriginal;
      }
    });
  });
})();
