import "@testing-library/jest-dom";

// ========== MOCKS ESSENTIELS ==========

// Mock pour Vite import.meta.env
Object.defineProperty(global, "import", {
  value: {
    meta: {
      env: {
        VITE_API_URL: "http://localhost:3000",
        MODE: "test",
      },
    },
  },
  writable: true,
});

// Mock localStorage simplifié
const localStorageMock = {
  store: {},
  getItem: jest.fn((key) => localStorageMock.store[key] || "mock-token"),
  setItem: jest.fn((key, value) => {
    localStorageMock.store[key] = value;
  }),
  removeItem: jest.fn((key) => {
    delete localStorageMock.store[key];
  }),
  clear: jest.fn(() => {
    localStorageMock.store = {};
  }),
};

global.localStorage = localStorageMock;

// Mock fetch
global.fetch = jest.fn(() =>
  Promise.resolve({
    ok: true,
    json: () => Promise.resolve({}),
  }),
);

// Mock window functions
window.scrollTo = jest.fn();

// ========== SUPPRIMER LES WARNINGS ==========

// Stocker les originaux
const originalWarn = console.warn;
const originalError = console.error;

// Activer avant tous les tests
beforeAll(() => {
  // Ignorer TOUS les warnings pendant les tests
  console.warn = jest.fn();
  console.error = jest.fn();

  // Optionnel: ignorer aussi console.log
  // console.log = jest.fn();
});

// Restaurer après tous les tests
afterAll(() => {
  console.warn = originalWarn;
  console.error = originalError;
});

// ========== CONFIGURATION AVANT CHAQUE TEST ==========

beforeEach(() => {
  // Réinitialiser tous les mocks Jest
  jest.clearAllMocks();

  // Réinitialiser le store localStorage
  localStorageMock.store = {};
  localStorageMock.getItem.mockClear();
  localStorageMock.setItem.mockClear();
  localStorageMock.removeItem.mockClear();
  localStorageMock.clear.mockClear();

  // Réinitialiser fetch avec un mock par défaut
  fetch.mockImplementation(() =>
    Promise.resolve({
      ok: true,
      json: () => Promise.resolve({}),
    }),
  );

  // Définir un token par défaut
  localStorageMock.getItem.mockReturnValue("test-token-123");
});

// ========== FONCTIONS UTILITAIRES ==========

// Helper pour les tests async
global.waitForPromises = () => new Promise(setImmediate);
