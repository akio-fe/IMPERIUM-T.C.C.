const formatBR = (value) => {
  const number = typeof value === "number" ? value : Number(value) || 0;
  return number.toFixed(2).replace(".", ",");
};

const CART_API = window.CARRINHO_API || {};
const CHECKOUT_API = window.CHECKOUT_API || {};
const LOGIN_URL = window.CARRINHO_LOGIN_URL || "/";
const ADD_ADDRESS_URL = window.CHECKOUT_ADD_ADDRESS_URL || "";
const isAuthenticated = Boolean(window.CARRINHO_IS_AUTHENTICATED);

const state = {
  itens: [],
  subtotal: 0,
};

const enderecoState = {
  carregado: false,
  itens: [],
  selecionado: null,
};

let freteSelecionado = 0;

const listaCarrinho = document.getElementById("lista-carrinho");
const subtotalEl = document.getElementById("subtotal");
const freteEl = document.getElementById("frete-valor");
const totalEl = document.getElementById("total");
const btnFinalizar = document.getElementById("btn-finalizar");

const telaCarrinho = document.getElementById("tela-carrinho");
const telaEnderecos = document.getElementById("tela-enderecos");
const telaProcessando = document.getElementById("tela-processando");
const listaEnderecosEl = document.getElementById("lista-enderecos");
const btnVoltarCarrinho = document.getElementById("btn-voltar-carrinho");
const btnIrPagamento = document.getElementById("btn-ir-pagamento");
const btnCancelarProcessamento = document.getElementById("btn-cancelar-processamento");

const escapeHtml = (value = "") =>
  value
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");

const mostrarAviso = (mensagem) => {
  const aviso = document.createElement("div");
  aviso.textContent = mensagem;
  aviso.style.position = "fixed";
  aviso.style.bottom = "24px";
  aviso.style.right = "24px";
  aviso.style.background = "#d4af37";
  aviso.style.color = "#111";
  aviso.style.padding = "12px 18px";
  aviso.style.borderRadius = "10px";
  aviso.style.fontWeight = "600";
  aviso.style.zIndex = "99999";
  aviso.style.boxShadow = "0 12px 24px rgba(0,0,0,0.25)";
  document.body.appendChild(aviso);
  setTimeout(() => aviso.remove(), 2500);
};

const setListaMensagem = (mensagem, isError = false) => {
  if (!listaCarrinho) {
    return;
  }
  listaCarrinho.innerHTML = `<p class="estado-lista${
    isError ? " estado-erro" : ""
  }">${escapeHtml(mensagem)}</p>`;
};

const setEnderecoMensagem = (mensagem, isError = false) => {
  if (!listaEnderecosEl) {
    return;
  }
  listaEnderecosEl.innerHTML = `<p class="estado-lista${
    isError ? " estado-erro" : ""
  }">${escapeHtml(mensagem)}</p>`;
  enderecoState.selecionado = null;
  habilitarPagamentoSePossivel();
};

const habilitarPagamentoSePossivel = () => {
  if (!btnIrPagamento) {
    return;
  }
  btnIrPagamento.disabled = !(enderecoState.selecionado > 0);
};

const buildEnderecoMarkup = (endereco) => {
  const id = Number(endereco.id || endereco.EndEntId || 0);
  const referencia = escapeHtml(
    endereco.referencia || endereco.label || endereco.EndEntRef || "Endereço"
  );
  const rua = endereco.rua || endereco.EndEntRua || "";
  const numero = endereco.numero || endereco.EndEntNum || "";
  const bairro = endereco.bairro || endereco.EndEntBairro || "";
  const cidade = endereco.cidade || endereco.EndEntCid || "";
  const estado = endereco.estado || endereco.EndEntEst || "";
  const cep = endereco.cep || endereco.EndEntCep || "";
  const complemento = endereco.complemento || endereco.EndEntComple || "";
  const descricao = `${rua}, ${numero} - ${bairro}, ${cidade} - ${estado}, CEP: ${cep}${
    complemento ? " - " + complemento : ""
  }`;

  return `
    <label class="endereco-card">
      <input type="radio" name="endereco-selecionado" value="${id}">
      <div class="endereco-card__info">
        <strong>${referencia}</strong>
        <p>${escapeHtml(descricao)}</p>
      </div>
    </label>
  `;
};

const renderizarEnderecos = (enderecos) => {
  if (!listaEnderecosEl) {
    return;
  }

  if (!enderecos.length) {
    if (ADD_ADDRESS_URL) {
      const safeUrl = escapeHtml(String(ADD_ADDRESS_URL));
      listaEnderecosEl.innerHTML = `
        <p class="estado-lista">
          Nenhum endereço encontrado. <a href="${safeUrl}" target="_blank" rel="noopener noreferrer">Cadastre um novo</a> para continuar.
        </p>`;
    } else {
      setEnderecoMensagem("Nenhum endereço encontrado. Cadastre um novo para continuar.");
    }
    enderecoState.selecionado = null;
    habilitarPagamentoSePossivel();
    return;
  }

  listaEnderecosEl.innerHTML = enderecos.map((item) => buildEnderecoMarkup(item)).join("");
  enderecoState.selecionado = Number(enderecos[0].id || enderecos[0].EndEntId || 0) || null;
  const firstRadio = listaEnderecosEl.querySelector('input[name="endereco-selecionado"]');
  if (firstRadio && enderecoState.selecionado) {
    firstRadio.checked = true;
  }
  habilitarPagamentoSePossivel();
};

const atualizarTotais = () => {
  if (subtotalEl) {
    subtotalEl.textContent = formatBR(state.subtotal);
  }
  if (freteEl) {
    freteEl.textContent = formatBR(freteSelecionado);
  }
  const total = state.subtotal + freteSelecionado;
  if (totalEl) {
    totalEl.textContent = formatBR(total);
  }
};

const verificarBotaoFinalizar = () => {
  if (!btnFinalizar) {
    return;
  }
  const temProduto = state.itens.length > 0;
  btnFinalizar.disabled = !(temProduto && isAuthenticated);
};

const aplicarSnapshot = (dados) => {
  state.itens = Array.isArray(dados.itens) ? dados.itens : [];
  state.subtotal = typeof dados.subtotal === "number" ? dados.subtotal : 0;
  renderizarCarrinho();
  atualizarTotais();
  verificarBotaoFinalizar();
};

const criarItemMarkup = (item) => {
  const quantidade = item.quantidade || 0;
  const precoFormatado = item.precoFormatado || formatBR(item.precoUnitario || 0);
  const totalFormatado =
    item.totalFormatado || formatBR((item.precoUnitario || 0) * quantidade);
  const imagem = escapeHtml(item.imagem || "");
  const nome = escapeHtml(item.nome || "Produto");
  const tamanho = escapeHtml(item.tamanho || "");

  return `
    <div class="item-carrinho">
      <img src="${imagem}" alt="${nome}">
      <div class="item-info">
        <h4>${nome}</h4>
        ${tamanho ? `<p>Tamanho: ${tamanho}</p>` : ""}
        <p>Quantidade: ${quantidade}</p>
        <p>Preço unitário: R$ ${precoFormatado}</p>
        <p>Total: R$ ${totalFormatado}</p>
        <div class="qtd-control" style="margin-top:8px;display:flex;align-items:center;gap:8px;">
          <button type="button" data-cart-action="decremento" data-item-id="${item.id}">-</button>
          <span>${quantidade}</span>
          <button type="button" data-cart-action="incremento" data-item-id="${item.id}">+</button>
          <button type="button" class="remover" data-cart-action="remover" data-item-id="${item.id}">Remover</button>
        </div>
      </div>
    </div>`;
};

const renderizarCarrinho = () => {
  if (!listaCarrinho) {
    return;
  }

  if (!state.itens.length) {
    setListaMensagem("Seu carrinho ainda está vazio.");
    return;
  }

  const markup = state.itens.map((item) => criarItemMarkup(item)).join("");
  listaCarrinho.innerHTML = markup;
};

const requestJson = async (url, options = {}) => {
  const config = { credentials: "same-origin", ...options };
  const headers = { "Content-Type": "application/json", ...(config.headers || {}) };
  config.headers = headers;
  if (config.body && typeof config.body !== "string") {
    config.body = JSON.stringify(config.body);
  }

  const response = await fetch(url, config);
  if (response.status === 401) {
    window.location.href = LOGIN_URL;
    return null;
  }

  let payload = null;
  try {
    payload = await response.json();
  } catch (_) {
    payload = null;
  }

  if (!response.ok || !payload?.sucesso) {
    throw new Error(payload?.mensagem || "Não foi possível processar a solicitação.");
  }

  return payload;
};

const carregarCarrinho = async () => {
  if (!listaCarrinho || !CART_API.listar) {
    return;
  }

  setListaMensagem("Carregando itens do carrinho...");

  try {
    const payload = await requestJson(CART_API.listar, { method: "GET" });
    if (!payload) {
      return;
    }
    aplicarSnapshot(payload);
  } catch (error) {
    setListaMensagem(error.message || "Não foi possível carregar o carrinho.", true);
  }
};

const atualizarItem = async (itemId, delta) => {
  if (!CART_API.atualizar) {
    return;
  }
  try {
    const payload = await requestJson(CART_API.atualizar, {
      method: "POST",
      body: { itemId, delta },
    });
    if (!payload) {
      return;
    }
    aplicarSnapshot(payload);
  } catch (error) {
    mostrarAviso(error.message || "Falha ao atualizar o item.");
  }
};

const removerItem = async (itemId) => {
  if (!CART_API.remover) {
    return;
  }
  try {
    const payload = await requestJson(CART_API.remover, {
      method: "POST",
      body: { itemId },
    });
    if (!payload) {
      return;
    }
    aplicarSnapshot(payload);
  } catch (error) {
    mostrarAviso(error.message || "Falha ao remover o item.");
  }
};

const carregarEnderecos = async () => {
  if (!listaEnderecosEl || !CHECKOUT_API.enderecos) {
    return;
  }

  setEnderecoMensagem("Carregando endereços...");

  try {
    const payload = await requestJson(CHECKOUT_API.enderecos, { method: "GET" });
    if (!payload) {
      throw new Error("Não foi possível carregar os endereços.");
    }
    const itens = Array.isArray(payload.enderecos) ? payload.enderecos : [];
    enderecoState.itens = itens;
    enderecoState.carregado = true;
    renderizarEnderecos(itens);
  } catch (error) {
    setEnderecoMensagem(error.message || "Não foi possível carregar os endereços.", true);
  }
};

const mostrarTela = (nome) => {
  if (telaCarrinho) {
    telaCarrinho.style.display = nome === "carrinho" ? "block" : "none";
  }
  if (telaEnderecos) {
    telaEnderecos.style.display = nome === "enderecos" ? "block" : "none";
  }
  if (telaProcessando) {
    telaProcessando.style.display = nome === "processando" ? "block" : "none";
  }
};

const irParaEnderecos = () => {
  if (!isAuthenticated) {
    window.location.href = LOGIN_URL;
    return;
  }
  if (!state.itens.length) {
    mostrarAviso("Seu carrinho está vazio.");
    return;
  }
  mostrarTela("enderecos");
  if (!enderecoState.carregado) {
    carregarEnderecos();
  }
};

const iniciarPagamento = async () => {
  if (!CHECKOUT_API.criarPedido) {
    mostrarAviso("Fluxo de pagamento indisponível no momento.");
    return;
  }

  if (!(enderecoState.selecionado > 0)) {
    mostrarAviso("Selecione um endereço para continuar.");
    return;
  }

  mostrarTela("processando");

  try {
    const payload = await requestJson(CHECKOUT_API.criarPedido, {
      method: "POST",
      body: {
        enderecoId: enderecoState.selecionado,
        freteValor: freteSelecionado,
      },
    });

    if (!payload) {
      throw new Error("Não foi possível iniciar o pagamento.");
    }

    if (payload.pagamentoUrl) {
      window.location.href = payload.pagamentoUrl;
      return;
    }

    throw new Error(payload.mensagem || "Não foi possível iniciar o pagamento.");
  } catch (error) {
    mostrarTela("enderecos");
    mostrarAviso(error.message || "Falha ao redirecionar para o pagamento.");
  }
};

const consultarFrete = async () => {
  const cepInput = document.getElementById("input-cep");
  const resultado = document.getElementById("resultado-frete");
  const mapa = document.getElementById("mapa-frete");
  if (!cepInput || !resultado || !mapa) {
    return;
  }

  const cep = cepInput.value.replace(/\D/g, "");
  resultado.innerHTML = "";
  mapa.innerHTML = "";

  if (cep.length !== 8) {
    resultado.innerHTML = "<p>CEP inválido. Digite 8 números.</p>";
    freteSelecionado = 0;
    atualizarTotais();
    return;
  }

  resultado.innerHTML = '<p style="color:#aaa">Consultando...</p>';
  try {
    const response = await fetch(`https://viacep.com.br/ws/${cep}/json/`);
    const data = await response.json();
    if (data.erro) {
      resultado.innerHTML = "<p>CEP não encontrado.</p>";
      freteSelecionado = 0;
      atualizarTotais();
      return;
    }

    const logradouro = data.logradouro || "Rua não informada";
    const cidade = data.localidade || "";
    const uf = data.uf || "";

    let valorFrete = 0;
    let prazo = "";
    if (uf === "SP") {
      if (cidade.toLowerCase() === "são paulo" || cidade.toLowerCase() === "sao paulo") {
        valorFrete = 15;
        prazo = "1 a 2 dias úteis";
      } else {
        valorFrete = 25;
        prazo = "2 a 4 dias úteis";
      }
    } else if (["RJ", "MG", "PR"].includes(uf)) {
      valorFrete = 35;
      prazo = "3 a 6 dias úteis";
    } else {
      valorFrete = 50;
      prazo = "5 a 9 dias úteis";
    }

    freteSelecionado = valorFrete;
    resultado.innerHTML = `
      <p><strong>${escapeHtml(logradouro)}</strong></p>
      <p>${escapeHtml(cidade)} - ${escapeHtml(uf)}</p>
      <p>Frete: <strong>R$ ${formatBR(valorFrete)}</strong></p>
      <p>Prazo: <strong>${prazo}</strong></p>
    `;

    mapa.innerHTML = `
      <iframe width="100%" height="200" frameborder="0" style="border:0"
        src="https://www.google.com/maps?q=${encodeURIComponent(
          `${logradouro}, ${cidade} - ${uf}`
        )}&output=embed"></iframe>
    `;

    atualizarTotais();
  } catch (error) {
    console.error(error);
    resultado.innerHTML = "<p>Erro ao consultar o CEP.</p>";
    freteSelecionado = 0;
    atualizarTotais();
  }
};

const btnCep = document.getElementById("btn-cep");
const inputCep = document.getElementById("input-cep");
if (btnCep) {
  btnCep.addEventListener("click", consultarFrete);
}
if (inputCep) {
  inputCep.addEventListener("keypress", (event) => {
    if (event.key === "Enter") {
      consultarFrete();
    }
  });
}

if (listaCarrinho) {
  listaCarrinho.addEventListener("click", (event) => {
    const button = event.target.closest("[data-cart-action]");
    if (!button) {
      return;
    }
    const itemId = Number(button.dataset.itemId);
    if (!itemId) {
      return;
    }

    const action = button.dataset.cartAction;
    if (action === "incremento") {
      atualizarItem(itemId, 1);
    } else if (action === "decremento") {
      atualizarItem(itemId, -1);
    } else if (action === "remover") {
      removerItem(itemId);
    }
  });
}

if (listaEnderecosEl) {
  listaEnderecosEl.addEventListener("change", (event) => {
    const input = event.target.closest('input[name="endereco-selecionado"]');
    if (!input) {
      return;
    }
    enderecoState.selecionado = Number(input.value) || null;
    habilitarPagamentoSePossivel();
  });
}

if (btnFinalizar) {
  btnFinalizar.addEventListener("click", () => {
    if (!isAuthenticated) {
      window.location.href = LOGIN_URL;
      return;
    }
    irParaEnderecos();
  });
}

if (btnVoltarCarrinho) {
  btnVoltarCarrinho.addEventListener("click", () => {
    mostrarTela("carrinho");
  });
}

if (btnCancelarProcessamento) {
  btnCancelarProcessamento.addEventListener("click", () => {
    mostrarTela("enderecos");
  });
}

if (btnIrPagamento) {
  btnIrPagamento.addEventListener("click", iniciarPagamento);
}

mostrarTela("carrinho");
carregarCarrinho();
