const FAVORITOS_KEY = "favoritos";
const CARRINHO_KEY = "carrinho";

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

const initTamanhos = () => {
  const botoes = document.querySelectorAll(".tamanhos button");
  if (!botoes.length) {
    return;
  }
  botoes.forEach((botao) => {
    botao.addEventListener("click", () => {
      botoes.forEach((item) => item.classList.remove("selected"));
      botao.classList.add("selected");
    });
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
  if (!produto) {
    return;
  }
  const toggle = document.getElementById("btn-favoritar");
  if (!toggle) {
    return;
  }

  const isFavorito = () =>
    readStorage(FAVORITOS_KEY).some((item) => item.id === produto.id);
  const syncToggle = () => {
    toggle.checked = isFavorito();
  };

  toggle.addEventListener("change", () => {
    const favoritos = readStorage(FAVORITOS_KEY).filter(
      (item) => item.id !== produto.id
    );
    if (toggle.checked) {
      favoritos.push({
        id: produto.id,
        nome: produto.nome,
        imagem: produto.imagem,
        preco: produto.preco,
        link: produto.link,
      });
    }
    writeStorage(FAVORITOS_KEY, favoritos);
  });

  window.addEventListener("storage", (event) => {
    if (event.key === FAVORITOS_KEY) {
      syncToggle();
    }
  });

  syncToggle();
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

    const carrinho = readStorage(CARRINHO_KEY);
    carrinho.push({
      id: produto.id,
      nome: produto.nome,
      imagem: produto.imagem,
      preco: produto.preco,
      tamanho: tamanhoSelecionado.textContent.trim(),
      qtd: 1,
    });
    writeStorage(CARRINHO_KEY, carrinho);
    exibirToast("Produto adicionado ao carrinho!");
  });
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
    const THREE = await import(
      "https://cdn.skypack.dev/three@0.129.0/build/three.module.js"
    );
    const { OrbitControls } = await import(
      "https://cdn.skypack.dev/three@0.129.0/examples/jsm/controls/OrbitControls.js"
    );
    const { GLTFLoader } = await import(
      "https://cdn.skypack.dev/three@0.129.0/examples/jsm/loaders/GLTFLoader.js"
    );

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
    let currentModel = null;

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

const initProdutoPage = () => {
  const produto = parseProdutoData();
  initSearchBar();
  initTamanhos();
  initFavorito(produto);
  initCarrinho(produto);
  initThreeViewer(produto?.modelPath);
};

document.addEventListener("DOMContentLoaded", initProdutoPage);
