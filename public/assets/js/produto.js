// Imports do Three.js (usando versão estável)
import * as THREE from 'https://cdn.skypack.dev/three@0.129.0';
import { GLTFLoader } from 'https://cdn.skypack.dev/three@0.129.0/examples/jsm/loaders/GLTFLoader.js';
import { OrbitControls } from 'https://cdn.skypack.dev/three@0.129.0/examples/jsm/controls/OrbitControls.js';

// Torna THREE disponível globalmente para compatibilidade
window.THREE = THREE;

const resolvePublicRoot = () => {
  const { pathname } = window.location;
  const publicIndex = pathname.indexOf("/public/");
  if (publicIndex === -1) {
    return "";
  }
  return `${pathname.slice(0, publicIndex)}/public`;
};

const PUBLIC_ROOT = resolvePublicRoot();
const CARRINHO_KEY = "carrinho";
const FAVORITOS_API_URL = `${PUBLIC_ROOT}/api/favoritos.php`;
const LOGIN_PAGE = `${PUBLIC_ROOT}/pages/auth/cadastro_login.html`;
const LOGIN_POPUP_ID = "login-required-popup";

// Variável global para armazenar o modelo 3D atual
let currentModel = null;

const ensureLoginPopup = (() => {
  let created = false;
  return () => {
    if (created) {
      return document.getElementById(LOGIN_POPUP_ID);
    }
    const style = document.createElement("style");
    style.textContent = `
      #${LOGIN_POPUP_ID} {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.6);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10000;
        padding: 20px;
      }
      #${LOGIN_POPUP_ID}.hidden { display: none; }
      #${LOGIN_POPUP_ID} .login-popup__card {
        background: #111;
        color: #f5f5f5;
        border: 1px solid #333;
        border-radius: 12px;
        max-width: 420px;
        width: 100%;
        padding: 32px;
        box-shadow: 0 20px 50px rgba(0,0,0,0.4);
        font-family: "Inter", sans-serif;
      }
      body.light #${LOGIN_POPUP_ID} .login-popup__card {
        background: #fff;
        color: #111;
        border-color: #ddd;
      }
      #${LOGIN_POPUP_ID} .login-popup__title {
        font-size: 1.3rem;
        font-weight: 600;
        margin-bottom: 12px;
      }
      #${LOGIN_POPUP_ID} .login-popup__text {
        font-size: 0.95rem;
        line-height: 1.4;
        margin-bottom: 24px;
      }
      #${LOGIN_POPUP_ID} .login-popup__actions {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        justify-content: flex-end;
      }
      #${LOGIN_POPUP_ID} .login-popup__actions button,
      #${LOGIN_POPUP_ID} .login-popup__actions a {
        border: 0;
        border-radius: 999px;
        padding: 10px 18px;
        cursor: pointer;
        font-weight: 600;
        font-size: 0.95rem;
      }
      #${LOGIN_POPUP_ID} .login-popup__actions .login-popup__cancel {
        background: transparent;
        color: #d4af37;
        border: 1px solid #d4af37;
      }
      body.light #${LOGIN_POPUP_ID} .login-popup__actions .login-popup__cancel {
        color: #111;
        border-color: #111;
      }
      #${LOGIN_POPUP_ID} .login-popup__actions .login-popup__confirm {
        background: #d4af37;
        color: #111;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 140px;
      }
      #${LOGIN_POPUP_ID} .login-popup__close {
        position: absolute;
        top: 16px;
        right: 16px;
        background: transparent;
        border: 0;
        color: inherit;
        font-size: 1.2rem;
        cursor: pointer;
      }
    `;
    document.head.appendChild(style);

    const overlay = document.createElement("div");
    overlay.id = LOGIN_POPUP_ID;
    overlay.className = "hidden";
    overlay.innerHTML = `
      <div class="login-popup__card">
        <button type="button" class="login-popup__close" aria-label="Fechar">×</button>
        <h2 class="login-popup__title">Faça login para favoritar</h2>
        <p class="login-popup__text">
          Você precisa estar logado para salvar itens nos favoritos.
        </p>
        <div class="login-popup__actions">
          <button type="button" class="login-popup__cancel">Agora não</button>
          <a class="login-popup__confirm" href="${LOGIN_PAGE}">Fazer login</a>
        </div>
      </div>`;
    document.body.appendChild(overlay);

    overlay.addEventListener("click", (event) => {
      if (event.target === overlay) {
        overlay.classList.add("hidden");
      }
    });
    overlay.querySelector(".login-popup__close").addEventListener("click", () => {
      overlay.classList.add("hidden");
    });
    overlay.querySelector(".login-popup__cancel").addEventListener("click", () => {
      overlay.classList.add("hidden");
    });

    created = true;
    return overlay;
  };
})();

const showLoginPopup = () => {
  const overlay = ensureLoginPopup();
  overlay.classList.remove("hidden");
};

const parseProdutoData = () => {
  const container = document.querySelector(".container-produto");
  if (!container || !container.dataset.produto) {
    return null;
  }
  try {
    return JSON.parse(container.dataset.produto);
  } catch (error) {
    console.error("Produto inválido:", error);
    return null;
  }
};

const initSearchBar = () => {
  const input = document.querySelector(".search-bar input");
  const fechar = document.querySelector(".search-bar .fechar");
  const lupa = document.querySelector(".icons .pesquisar");
  const icon = document.querySelector(".search-bar .search-icon");

  if (!input || !fechar || !lupa || !icon) {
    return;
  }

  lupa.addEventListener("click", () => {
    input.classList.add("mostrar");
    fechar.style.display = "inline-block";
    lupa.style.display = "none";
    icon.style.display = "block";
    input.focus();
  });

  fechar.addEventListener("click", () => {
    input.classList.remove("mostrar");
    fechar.style.display = "none";
    lupa.style.display = "inline-block";
    icon.style.display = "none";
    input.value = "";
  });
};

const initTamanhos = (produto) => {
  const container = document.querySelector(".tamanhos");
  if (!container) {
    return;
  }

  // Define os tamanhos baseado na categoria do produto
  let tamanhos = [];
  if (produto && produto.categoriaId === 1) {
    // Calçados: tamanhos numéricos
    tamanhos = ['38', '39', '40', '41', '42', '43', '44'];
  } else {
    // Outras categorias: tamanhos alfabéticos
    tamanhos = ['PP', 'P', 'M', 'G', 'GG', 'XGG'];
  }

  // Limpa o container e adiciona os botões
  container.innerHTML = '';
  tamanhos.forEach((tamanho) => {
    const botao = document.createElement('button');
    botao.type = 'button';
    botao.textContent = tamanho;
    botao.addEventListener('click', () => {
      container.querySelectorAll('button').forEach((item) => item.classList.remove('selected'));
      botao.classList.add('selected');
    });
    container.appendChild(botao);
  });
};

const readStorage = (key) => {
  try {
    return JSON.parse(localStorage.getItem(key)) || [];
  } catch (_) {
    return [];
  }
};

const writeStorage = (key, value) => {
  localStorage.setItem(key, JSON.stringify(value));
};

const parseApiResponse = async (response) => {
  let payload = null;
  try {
    payload = await response.json();
  } catch (_) {
    payload = null;
  }
  if (!response.ok) {
    const message = payload?.message || "Não foi possível atualizar favoritos.";
    const error = new Error(message);
    error.status = response.status;
    throw error;
  }
  return payload;
};

const updateFavorite = async (produtoId, shouldFavorite) => {
  const response = await fetch(FAVORITOS_API_URL, {
    method: shouldFavorite ? "POST" : "DELETE",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify({ produtoId }),
    credentials: "same-origin",
  });
  return parseApiResponse(response);
};

const viewerStabilityGuard = (() => {
  const COOLDOWN_MS = 5 * 60 * 1000;
  let disabledUntil = 0;
  return {
    isDisabled() {
      return Date.now() < disabledUntil;
    },
    triggerCooldown() {
      disabledUntil = Date.now() + COOLDOWN_MS;
    },
  };
})();

const imageBitmapToggle = (() => {
  let lockCount = 0;
  let originalCreateImageBitmap = null;
  return {
    disable() {
      if (typeof window === "undefined") {
        return () => {};
      }
      if (
        typeof window.createImageBitmap === "undefined" &&
        !originalCreateImageBitmap
      ) {
        return () => {};
      }
      if (!lockCount) {
        originalCreateImageBitmap =
          originalCreateImageBitmap || window.createImageBitmap || null;
        if (typeof window.createImageBitmap !== "undefined") {
          window.createImageBitmap = undefined;
        }
      }
      lockCount += 1;
      return () => {
        if (!lockCount) {
          return;
        }
        lockCount -= 1;
        if (!lockCount && originalCreateImageBitmap) {
          window.createImageBitmap = originalCreateImageBitmap;
          originalCreateImageBitmap = null;
        }
      };
    },
  };
})();

const shouldRetryWithoutImageBitmap = (error) => {
  const message =
    error?.message || error?.error?.message || String(error || "");
  return /ImageBitmap|could not be allocated/i.test(message);
};

const isWebGLOutOfMemory = (error) => {
  const message =
    error?.message || error?.error?.message || String(error || "");
  return /out_of_memory|context lost/i.test(message);
};

const isWebGLContextInitFailure = (error) => {
  const message =
    error?.message || error?.error?.message || String(error || "");
  return /webgl context|context.*creation/i.test(message);
};

const initFavorito = (produto) => {
  if (!produto || !produto.id) {
    return;
  }
  const toggle = document.getElementById("btn-favoritar");
  if (!toggle) {
    return;
  }

  const requiresLogin = !produto.isAuthenticated;
  const setToggle = (value) => {
    toggle.checked = Boolean(value);
  };

  setToggle(produto.favorito);

  if (requiresLogin) {
    toggle.addEventListener("change", () => {
      setToggle(false);
      showLoginPopup();
    });
    return;
  }

  toggle.addEventListener("change", async () => {
    if (toggle.dataset.loading === "1") {
      return;
    }
    toggle.dataset.loading = "1";
    toggle.disabled = true;
    try {
      await updateFavorite(produto.id, toggle.checked);
      produto.favorito = toggle.checked;
      exibirToast(
        toggle.checked
          ? "Produto adicionado aos favoritos."
          : "Produto removido dos favoritos."
      );
    } catch (error) {
      console.error("Favoritos", error);
      setToggle(!toggle.checked);
      const message = error?.message || "Não foi possível atualizar favoritos.";
      exibirToast(message);
      if (error?.status === 401) {
        showLoginPopup();
      }
    } finally {
      toggle.disabled = false;
      delete toggle.dataset.loading;
    }
  });
};

const exibirToast = (mensagem) => {
  const aviso = document.createElement("div");
  aviso.textContent = mensagem;
  aviso.style.position = "fixed";
  aviso.style.bottom = "20px";
  aviso.style.right = "20px";
  aviso.style.background = "#d4af37";
  aviso.style.color = "#000";
  aviso.style.padding = "12px 18px";
  aviso.style.borderRadius = "10px";
  aviso.style.fontWeight = "600";
  aviso.style.zIndex = "99999";
  aviso.style.boxShadow = "0 10px 20px rgba(0,0,0,0.25)";
  document.body.appendChild(aviso);
  setTimeout(() => aviso.remove(), 2000);
};

let viewerErrorNotified = false;
const notifyViewerIssue = () => {
  if (viewerErrorNotified) {
    return;
  }
  viewerErrorNotified = true;
  exibirToast("Visualização 3D indisponível no momento.");
};

const initCarrinho = (produto) => {
  if (!produto) {
    return;
  }
  const botao = document.getElementById("btn-add-cart");
  if (!botao) {
    return;
  }

  botao.addEventListener("click", () => {
    const tamanhoSelecionado = document.querySelector(
      ".tamanhos button.selected"
    );
    if (!tamanhoSelecionado) {
      alert("Por favor, selecione um tamanho.");
      return;
    }

    const tamanho = tamanhoSelecionado.textContent.trim();
    const carrinho = readStorage(CARRINHO_KEY);
    const itemExistente = carrinho.find(
      (item) => item.id === produto.id && item.tamanho === tamanho
    );

    if (itemExistente) {
      itemExistente.qtd += 1;
    } else {
      carrinho.push({
        id: produto.id,
        nome: produto.nome,
        imagem: produto.imagem,
        preco: produto.preco,
        tamanho,
        qtd: 1,
      });
    }
    writeStorage(CARRINHO_KEY, carrinho);
    exibirToast("Produto adicionado ao carrinho!");
  });
};

const initThreeViewerDuplo = async (modelPathSuperior, modelPathInferior, options = {}) => {
  console.log('*** initThreeViewerDuplo CHAMADO ***');
  console.log('ModelPath Superior recebido:', modelPathSuperior);
  console.log('ModelPath Inferior recebido:', modelPathInferior);
  
  const container3D = document.getElementById("container3D");
  if (!container3D) {
    console.error('Container 3D não encontrado!');
    return;
  }

  if (viewerStabilityGuard.isDisabled()) {
    console.warn('Viewer desabilitado por instabilidade');
    container3D.classList.add("placeholder");
    notifyViewerIssue();
    return;
  }

  const { disableImageBitmap = false } = options;
  let releaseImageBitmap = null;
  if (disableImageBitmap) {
    releaseImageBitmap = imageBitmapToggle.disable();
  }
  const releaseImageBitmapOnce = () => {
    if (releaseImageBitmap) {
      releaseImageBitmap();
      releaseImageBitmap = null;
    }
  };
  
  const showPlaceholder = () => {
    container3D.classList.add("placeholder");
  };

  if (!modelPathSuperior && !modelPathInferior) {
    console.warn('Nenhum modelo fornecido!');
    showPlaceholder();
    releaseImageBitmapOnce();
    return;
  }

  container3D.classList.remove("placeholder");
  container3D.innerHTML = "";

  try {
    const scene = new THREE.Scene();
    scene.background = new THREE.Color(0x333333);

    const camera = new THREE.PerspectiveCamera(45, container3D.clientWidth / container3D.clientHeight, 0.1, 1000);
    const renderer = new THREE.WebGLRenderer({ antialias: true });
    renderer.setSize(container3D.clientWidth, container3D.clientHeight);
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));

    const onResize = () => {
      camera.aspect = container3D.clientWidth / container3D.clientHeight;
      camera.updateProjectionMatrix();
      renderer.setSize(container3D.clientWidth, container3D.clientHeight);
    };
    window.addEventListener("resize", onResize);

    const onContextLost = (evt) => {
      evt.preventDefault();
      console.warn("Contexto WebGL perdido");
    };
    const onContextRestored = () => {
      console.info("Contexto WebGL restaurado");
    };
    renderer.domElement.addEventListener("webglcontextlost", onContextLost);
    renderer.domElement.addEventListener("webglcontextrestored", onContextRestored);

    const showPlaceholder = () => {
      container3D.classList.add("placeholder");
      container3D.innerHTML = "";
    };

    container3D.appendChild(renderer.domElement);

    const ambientLight = new THREE.AmbientLight(0xffffff, 1.8);
    scene.add(ambientLight);

    const directionalLight = new THREE.DirectionalLight(0xffffff, 1.5);
    directionalLight.position.set(3, 5, 5);
    scene.add(directionalLight);

    const controls = new OrbitControls(camera, renderer.domElement);
    controls.enableDamping = true;
    controls.enableZoom = false;
    controls.autoRotate = false;
    controls.minPolarAngle = Math.PI / 4;
    controls.maxPolarAngle = Math.PI / 2;

    const loader = new GLTFLoader();
    let animationFrame = 0;

    const disposeMaterial = (material) => {
      if (!material) return;
      if (Array.isArray(material)) {
        material.forEach(disposeMaterial);
        return;
      }
      Object.keys(material).forEach((key) => {
        const value = material[key];
        if (value && typeof value.dispose === "function") {
          value.dispose();
        }
      });
      material.dispose?.();
    };

    const disposeSceneObjects = () => {
      if (!currentModel) return;
      currentModel.traverse((child) => {
        if (child.isMesh) {
          child.geometry?.dispose?.();
          disposeMaterial(child.material);
        }
      });
      scene.remove(currentModel);
      currentModel = null;
    };

    const cleanup = () => {
      window.removeEventListener("resize", onResize);
      renderer.domElement.removeEventListener("webglcontextlost", onContextLost);
      renderer.domElement.removeEventListener("webglcontextrestored", onContextRestored);
      cancelAnimationFrame(animationFrame);
      disposeSceneObjects();
      renderer.dispose();
      controls.dispose();
    };

    // Grupo para conter ambos os modelos
    const grupoCompleto = new THREE.Group();
    let modelosCarregados = 0;
    const totalModelos = (modelPathSuperior ? 1 : 0) + (modelPathInferior ? 1 : 0);

    const finalizarCarregamento = () => {
      if (modelosCarregados < totalModelos) return;

      // Centralizar e escalar o conjunto completo (modelos maiores e mais próximos)
      const box = new THREE.Box3().setFromObject(grupoCompleto);
      const size = box.getSize(new THREE.Vector3());
      const maxDimension = Math.max(size.x, size.y, size.z) || 1;
      const NORMALIZED_SIZE = 3.5; // Aumentado de 2.5 para 3.5
      const normalizedScale = NORMALIZED_SIZE / maxDimension;
      grupoCompleto.scale.setScalar(normalizedScale);

      box.setFromObject(grupoCompleto);
      const center = box.getCenter(new THREE.Vector3());
      grupoCompleto.position.sub(center);
      
      // Aproximar os modelos um do outro
      grupoCompleto.children.forEach((child, index) => {
        if (index === 1) {
          child.position.z -= 0.3; // Move o segundo modelo mais próximo
        }
      });

      scene.add(grupoCompleto);
      currentModel = grupoCompleto;      const sphere = box.getBoundingSphere(new THREE.Sphere());
      const radius = sphere.radius || 1;
      const fitOffset = 1.2;
      const halfFov = THREE.MathUtils.degToRad(camera.fov / 2);
      const distance = (radius / Math.sin(halfFov)) * fitOffset;
      camera.position.set(0, radius * 0.35, distance);
      controls.target.set(0, 0, 0);
      controls.update();

      releaseImageBitmapOnce();
    };

    // Carregar modelo superior
    if (modelPathSuperior) {
      loader.load(
        modelPathSuperior,
        (gltf) => {
          grupoCompleto.add(gltf.scene);
          modelosCarregados++;
          console.log('Modelo superior carregado');
          finalizarCarregamento();
        },
        undefined,
        (error) => {
          console.error("Erro ao carregar modelo superior", error);
          modelosCarregados++;
          finalizarCarregamento();
        }
      );
    }

    // Carregar modelo inferior
    if (modelPathInferior) {
      loader.load(
        modelPathInferior,
        (gltf) => {
          grupoCompleto.add(gltf.scene);
          modelosCarregados++;
          console.log('Modelo inferior carregado');
          finalizarCarregamento();
        },
        undefined,
        (error) => {
          console.error("Erro ao carregar modelo inferior", error);
          modelosCarregados++;
          finalizarCarregamento();
        }
      );
    }

    const animate = () => {
      animationFrame = requestAnimationFrame(animate);
      controls.update();
      renderer.render(scene, camera);
    };
    animate();
  } catch (error) {
    console.error("Erro ao iniciar viewer 3D duplo", error);
    if (isWebGLOutOfMemory(error) || isWebGLContextInitFailure(error)) {
      markViewerUnstable();
    }
    showPlaceholder();
    releaseImageBitmapOnce();
  }
};

const initThreeViewer = async (modelPath, options = {}) => {
  const container3D = document.getElementById("container3D");
  if (!container3D) {
    return;
  }

  if (viewerStabilityGuard.isDisabled()) {
    container3D.classList.add("placeholder");
    notifyViewerIssue();
    return;
  }

  const { disableImageBitmap = false } = options;
  let releaseImageBitmap = null;
  if (disableImageBitmap) {
    releaseImageBitmap = imageBitmapToggle.disable();
  }
  const releaseImageBitmapOnce = () => {
    if (releaseImageBitmap) {
      releaseImageBitmap();
      releaseImageBitmap = null;
    }
  };
  const markViewerUnstable = () => {
    viewerStabilityGuard.triggerCooldown();
    notifyViewerIssue();
  };

  const showPlaceholder = () => {
    container3D.classList.add("placeholder");
  };

  if (typeof container3D.__threeCleanup === "function") {
    container3D.__threeCleanup();
    container3D.__threeCleanup = null;
  }

  if (!modelPath) {
    showPlaceholder();
    releaseImageBitmapOnce();
    return;
  }

  while (container3D.firstChild) {
    container3D.removeChild(container3D.firstChild);
  }
  container3D.classList.remove("placeholder");

  const MAX_SIZE = 1000;
  const MIN_SIZE = 720;
  const getSize = () => {
    const rawWidth = container3D.clientWidth || container3D.offsetWidth || 0;
    const rawHeight = container3D.clientHeight || container3D.offsetHeight || 0;
    const width = Math.min(Math.max(rawWidth, MIN_SIZE), MAX_SIZE);
    const height = Math.min(Math.max(rawHeight, MIN_SIZE), MAX_SIZE);
    return { width, height };
  };

  try {
    const scene = new THREE.Scene();
    scene.background = new THREE.Color("#333");

    const { width, height } = getSize();
    const camera = new THREE.PerspectiveCamera(45, width / height, 0.1, 1000);
    camera.position.set(0, 1.5, 5);

    let renderer;
    try {
      renderer = new THREE.WebGLRenderer({
        alpha: true,
        antialias: false,
        powerPreference: "low-power",
        preserveDrawingBuffer: false,
        stencil: false,
        precision: "mediump",
      });
    } catch (error) {
      console.error("Renderer indisponível", error);
      if (isWebGLContextInitFailure(error) || isWebGLOutOfMemory(error)) {
        markViewerUnstable();
      }
      showPlaceholder();
      releaseImageBitmapOnce();
      return;
    }
    renderer.setPixelRatio(1);
    renderer.setSize(width, height);
    container3D.appendChild(renderer.domElement);

    const ambientLight = new THREE.AmbientLight(0xffffff, 1.8);
    scene.add(ambientLight);

    const directionalLight = new THREE.DirectionalLight(0xffffff, 1.5);
    directionalLight.position.set(3, 5, 5);
    scene.add(directionalLight);

    const controls = new OrbitControls(camera, renderer.domElement);
    controls.enableDamping = true;
    controls.enableZoom = false;
    controls.autoRotate = false;
    controls.minPolarAngle = Math.PI / 4;
    controls.maxPolarAngle = Math.PI / 2;

    const loader = new GLTFLoader();

    let animationFrame = 0;

    const disposeMaterial = (material) => {
      if (!material) {
        return;
      }
      if (Array.isArray(material)) {
        material.forEach(disposeMaterial);
        return;
      }
      Object.keys(material).forEach((key) => {
        const value = material[key];
        if (value && typeof value.dispose === "function") {
          value.dispose();
        }
      });
      material.dispose?.();
    };

    const disposeSceneObjects = () => {
      if (!currentModel) {
        return;
      }
      currentModel.traverse((child) => {
        if (child.isMesh) {
          child.geometry?.dispose?.();
          disposeMaterial(child.material);
        }
      });
      scene.remove(currentModel);
      currentModel = null;
    };

    const cleanup = () => {
      window.removeEventListener("resize", onResize);
      renderer.domElement.removeEventListener(
        "webglcontextlost",
        onContextLost
      );
      renderer.domElement.removeEventListener(
        "webglcontextrestored",
        onContextRestored
      );
      cancelAnimationFrame(animationFrame);
      disposeSceneObjects();
      controls.dispose();
      renderer.forceContextLoss?.();
      renderer.dispose();
      container3D.__threeCleanup = null;
      releaseImageBitmapOnce();
    };

    const onContextLost = (event) => {
      event.preventDefault();
      markViewerUnstable();
      cleanup();
      showPlaceholder();
    };

    const onContextRestored = () => {
      markViewerUnstable();
      cleanup();
      showPlaceholder();
    };

    const onResize = () => {
      const size = getSize();
      camera.aspect = size.width / size.height;
      camera.updateProjectionMatrix();
      renderer.setSize(size.width, size.height);
    };

    renderer.domElement.addEventListener("webglcontextlost", onContextLost, {
      once: true,
    });
    renderer.domElement.addEventListener(
      "webglcontextrestored",
      onContextRestored,
      { once: true }
    );
    window.addEventListener("resize", onResize);

    container3D.__threeCleanup = cleanup;

    loader.load(
      modelPath,
      (gltf) => {
        const model = gltf.scene;

        // Normalize the model so every asset occupies roughly the same viewport area.
        const box = new THREE.Box3().setFromObject(model);
        const size = box.getSize(new THREE.Vector3());
        const maxDimension = Math.max(size.x, size.y, size.z) || 1;
        const NORMALIZED_SIZE = 2.5;
        const normalizedScale = NORMALIZED_SIZE / maxDimension;
        model.scale.setScalar(normalizedScale);

        // Recalculate center after scaling and move model so origin is near its center.
        box.setFromObject(model);
        const center = box.getCenter(new THREE.Vector3());
        model.position.sub(center);
        scene.add(model);
        currentModel = model;

        // Frame the model by moving camera based on bounding sphere radius.
        const sphere = box.getBoundingSphere(new THREE.Sphere());
        const radius = sphere.radius || 1;
        const fitOffset = 1.2;
        const halfFov = THREE.MathUtils.degToRad(camera.fov / 2);
        const distance = (radius / Math.sin(halfFov)) * fitOffset;
        camera.position.set(0, radius * 0.35, distance);
        controls.target.set(0, 0, 0);
        controls.update();

        releaseImageBitmapOnce();
      },
      undefined,
      (error) => {
        releaseImageBitmapOnce();
        if (isWebGLOutOfMemory(error)) {
          markViewerUnstable();
          cleanup();
          showPlaceholder();
          return;
        }
        if (!disableImageBitmap && shouldRetryWithoutImageBitmap(error)) {
          console.warn("Recarregando modelo 3D sem ImageBitmap.");
          cleanup();
          initThreeViewer(modelPath, { disableImageBitmap: true });
          return;
        }
        console.error("Falha ao carregar modelo 3D", error);
        cleanup();
        showPlaceholder();
      }
    );

    const animate = () => {
      animationFrame = requestAnimationFrame(animate);
      controls.update();
      renderer.render(scene, camera);
    };
    animate();
  } catch (error) {
    console.error("Erro ao iniciar viewer 3D", error);
    if (isWebGLOutOfMemory(error) || isWebGLContextInitFailure(error)) {
      markViewerUnstable();
    }
    showPlaceholder();
    releaseImageBitmapOnce();
  }
};

const initAlternanciaConjunto = (produto) => {
  // Verifica se é um conjunto (categoria 5=Masculino ou 11=Feminino)
  if (!produto || (produto.categoriaId !== 5 && produto.categoriaId !== 11)) {
    return;
  }

  const botoesPartes = document.querySelectorAll('.btn-parte');
  if (!botoesPartes.length) {
    return;
  }

  let parteAtual = 'completo';
  const modelPathOriginal = produto.modelPath;

  // Função para carregar modelo específico de cada parte
  const carregarModelo = async (parte) => {
    const pathSuperior = modelPathOriginal; // conjunto_X (com underscore)
    const pathInferior = modelPathOriginal.replace(/conjunto_(\d+)\//, 'conjunto$1/'); // conjuntoX (sem underscore)
    
    console.log(`Carregando modelo da parte: ${parte}`);
    console.log(`Path superior: ${pathSuperior}`);
    console.log(`Path inferior: ${pathInferior}`);
    
    if (parte === 'superior') {
      // Carrega apenas a parte superior
      initThreeViewer(pathSuperior);
    } else if (parte === 'inferior') {
      // Carrega apenas a parte inferior (calça)
      initThreeViewer(pathInferior);
    } else if (parte === 'completo') {
      // Para o completo, carrega ambos os modelos na mesma cena
      initThreeViewerDuplo(pathSuperior, pathInferior);
    }
  };

  botoesPartes.forEach((botao) => {
    botao.addEventListener('click', () => {
      const parte = botao.dataset.parte;
      if (parte === parteAtual) {
        return;
      }

      parteAtual = parte;

      // Atualiza estilos dos botões
      botoesPartes.forEach((btn) => {
        if (btn.dataset.parte === parte) {
          btn.style.background = '#2d3436';
          btn.style.color = 'white';
        } else {
          btn.style.background = '#dfe6e9';
          btn.style.color = '#2d3436';
        }
      });

      // Carrega o modelo da parte selecionada
      carregarModelo(parte);
      
      // Mostra feedback visual
      const textos = {
        'completo': 'Conjunto Completo',
        'superior': 'Parte Superior',
        'inferior': 'Calça'
      };
      exibirToast(`Visualizando: ${textos[parte]}`);
    });
  });
};

const initProdutoPage = () => {
  const produto = parseProdutoData();
  console.log('Produto carregado:', produto);
  
  initSearchBar();
  initTamanhos(produto);
  initFavorito(produto);
  initCarrinho(produto);
  initAlternanciaConjunto(produto);
  
  // Se for conjunto (categoria 5=Masculino ou 11=Feminino), carrega os dois modelos juntos
  if (produto && (produto.categoriaId === 5 || produto.categoriaId === 11) && produto.modelPath) {
    const pathSuperior = produto.modelPath; // conjunto_X (com underscore)
    const pathInferior = produto.modelPath.replace(/conjunto_(\d+)\//, 'conjunto$1/'); // conjuntoX (sem underscore)
    console.log('=== CARREGANDO CONJUNTO ===');
    console.log('Categoria ID:', produto.categoriaId);
    console.log('Path original:', produto.modelPath);
    console.log('Path Superior:', pathSuperior);
    console.log('Path Inferior:', pathInferior);
    initThreeViewerDuplo(pathSuperior, pathInferior);
  } else {
    // Para produtos normais, carrega modelo único
    console.log('=== CARREGANDO PRODUTO NORMAL ===');
    console.log('Model path:', produto?.modelPath);
    initThreeViewer(produto?.modelPath);
  }
};

document.addEventListener("DOMContentLoaded", initProdutoPage);
