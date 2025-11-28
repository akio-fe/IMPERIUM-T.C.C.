const resolvePublicRoot = () => {
  const { pathname } = window.location;
  const publicIndex = pathname.indexOf("/public/");
  if (publicIndex === -1) {
    return "";
  }
  return `${pathname.slice(0, publicIndex)}/public`;
};

const PUBLIC_ROOT = resolvePublicRoot();
const FAVORITOS_API_URL = `${PUBLIC_ROOT}/api/favoritos.php`;
const LOGIN_PAGE = `${PUBLIC_ROOT}/pages/auth/cadastro_login.html`;

const statusEl = document.getElementById("favoritosStatus");
const containerEl = document.getElementById("favoritosContainer");

const setStatus = (message, isError = false) => {
  if (!statusEl) {
    return;
  }
  statusEl.textContent = message;
  statusEl.style.color = isError ? "#e74c3c" : "var(--link-color, #aaa)";
};

const fetchFavoritos = async () => {
  const response = await fetch(FAVORITOS_API_URL, {
    headers: { Accept: "application/json" },
    credentials: "same-origin",
  });
  if (response.status === 401) {
    const payload = await response.json().catch(() => null);
    const msg = payload?.message || "Faça login para ver seus favoritos.";
    throw Object.assign(new Error(msg), { status: 401 });
  }
  const payload = await response.json();
  if (!payload?.success) {
    throw new Error(
      payload?.message || "Não foi possível carregar os favoritos."
    );
  }
  return Array.isArray(payload.data) ? payload.data : [];
};

const formatDateTime = (value) => {
  if (!value) {
    return "";
  }
  const date = new Date(value.replace(/ /g, "T"));
  if (Number.isNaN(date.getTime())) {
    return value;
  }
  return date.toLocaleDateString("pt-BR", {
    day: "2-digit",
    month: "short",
    year: "numeric",
  });
};

const createCard = (item) => {
  const article = document.createElement("article");
  article.className = "product";
  article.innerHTML = `
		<a href="${item.produto.link}" class="product__image-link">
			<img src="${item.produto.imagem}" alt="${item.produto.nome}">
		</a>
		<h3>${item.produto.nome}</h3>
		<p>${item.produto.valorFormatado}</p>
		<small>Favoritado em ${formatDateTime(item.data)}</small>
		<div class="product__actions">
			<a class="product__view" href="${item.produto.link}">Ver produto</a>
			<button class="remove-button" data-produto-id="${
        item.produto.id
      }">Remover</button>
		</div>`;
  return article;
};

const renderFavoritos = (items) => {
  if (!containerEl) {
    return;
  }
  containerEl.innerHTML = "";
  if (!items.length) {
    setStatus("Você ainda não favoritou nenhum produto.");
    return;
  }
  setStatus(
    `${items.length} produto${items.length === 1 ? "" : "s"} favoritado${
      items.length === 1 ? "" : "s"
    }.`
  );
  items.forEach((item) => {
    containerEl.appendChild(createCard(item));
  });
};

const removerFavorito = async (produtoId) => {
  const response = await fetch(FAVORITOS_API_URL, {
    method: "DELETE",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ produtoId }),
    credentials: "same-origin",
  });
  if (response.status === 401) {
    throw Object.assign(new Error("Faça login para gerenciar favoritos."), {
      status: 401,
    });
  }
  const payload = await response.json();
  if (!payload?.success) {
    throw new Error(payload?.message || "Erro ao remover favorito.");
  }
};

const handleRemoveClick = async (event) => {
  const button = event.target.closest("[data-produto-id]");
  if (!button) {
    return;
  }
  const produtoId = Number(button.dataset.produtoId);
  if (!produtoId) {
    return;
  }
  button.disabled = true;
  button.textContent = "Removendo...";
  try {
    await removerFavorito(produtoId);
    button.closest(".product")?.remove();
    if (!containerEl.children.length) {
      setStatus("Você ainda não favoritou nenhum produto.");
    } else {
      setStatus(
        `${containerEl.children.length} produto${
          containerEl.children.length === 1 ? "" : "s"
        } favoritado${containerEl.children.length === 1 ? "" : "s"}.`
      );
    }
  } catch (error) {
    alert(error.message || "Erro ao remover favorito.");
    if (error.status === 401) {
      window.location.href = LOGIN_PAGE;
    }
  } finally {
    if (button.isConnected) {
      button.disabled = false;
      button.textContent = "Remover";
    }
  }
};

const initFavoritosPage = async () => {
  if (!statusEl || !containerEl) {
    return;
  }
  setStatus("Carregando favoritos...");
  try {
    const itens = await fetchFavoritos();
    renderFavoritos(itens);
  } catch (error) {
    setStatus(
      error.message || "Não foi possível carregar seus favoritos.",
      true
    );
    if (error.status === 401) {
      const loginButton = document.createElement("button");
      loginButton.textContent = "Fazer login";
      loginButton.className = "remove-button";
      loginButton.addEventListener("click", () => {
        window.location.href = LOGIN_PAGE;
      });
      containerEl.appendChild(loginButton);
    }
  }
  containerEl.addEventListener("click", handleRemoveClick);
};

document.addEventListener("DOMContentLoaded", initFavoritosPage);
