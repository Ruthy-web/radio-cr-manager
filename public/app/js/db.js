/**
 * Stockage local hors ligne (R5) : IndexedDB, indépendant du serveur. Trois
 * magasins : `reports` (comptes rendus locaux, clé = client_uuid), `catalog`
 * (hôpitaux + examens mis en cache), `meta` (jeton d'accès, horodatage de
 * dernière synchronisation).
 */
const DB_NAME = 'radio-cr-manager';
const DB_VERSION = 1;

let dbPromise = null;

function openDb() {
  if (dbPromise) return dbPromise;

  dbPromise = new Promise((resolve, reject) => {
    const request = indexedDB.open(DB_NAME, DB_VERSION);

    request.onupgradeneeded = () => {
      const db = request.result;

      if (!db.objectStoreNames.contains('reports')) {
        const store = db.createObjectStore('reports', { keyPath: 'client_uuid' });
        store.createIndex('exam_date', 'exam_date');
        store.createIndex('dirty', 'dirty');
      }

      if (!db.objectStoreNames.contains('catalog')) {
        db.createObjectStore('catalog', { keyPath: 'id' });
      }

      if (!db.objectStoreNames.contains('meta')) {
        db.createObjectStore('meta', { keyPath: 'key' });
      }
    };

    request.onsuccess = () => resolve(request.result);
    request.onerror = () => reject(request.error);
  });

  return dbPromise;
}

async function tx(storeName, mode, fn) {
  const db = await openDb();

  return new Promise((resolve, reject) => {
    const transaction = db.transaction(storeName, mode);
    const store = transaction.objectStore(storeName);
    const result = fn(store);

    transaction.oncomplete = () => resolve(result);
    transaction.onerror = () => reject(transaction.error);
  });
}

function requestToPromise(request) {
  return new Promise((resolve, reject) => {
    request.onsuccess = () => resolve(request.result);
    request.onerror = () => reject(request.error);
  });
}

export const ReportsStore = {
  async put(report) {
    return tx('reports', 'readwrite', (store) => store.put(report));
  },

  async get(clientUuid) {
    const db = await openDb();
    const store = db.transaction('reports', 'readonly').objectStore('reports');
    return requestToPromise(store.get(clientUuid));
  },

  async all() {
    const db = await openDb();
    const store = db.transaction('reports', 'readonly').objectStore('reports');
    const all = await requestToPromise(store.getAll());
    return (all || []).filter((r) => !r.deleted);
  },

  async dirty() {
    const all = await this.all();
    return all.filter((r) => r.dirty);
  },

  async remove(clientUuid) {
    return tx('reports', 'readwrite', (store) => store.delete(clientUuid));
  },
};

export const CatalogStore = {
  async save(hospitals) {
    return tx('catalog', 'readwrite', (store) => store.put({ id: 'catalog', hospitals, cachedAt: new Date().toISOString() }));
  },

  async load() {
    const db = await openDb();
    const store = db.transaction('catalog', 'readonly').objectStore('catalog');
    const record = await requestToPromise(store.get('catalog'));
    return record ? record.hospitals : [];
  },
};

export const MetaStore = {
  async set(key, value) {
    return tx('meta', 'readwrite', (store) => store.put({ key, value }));
  },

  async get(key) {
    const db = await openDb();
    const store = db.transaction('meta', 'readonly').objectStore('meta');
    const record = await requestToPromise(store.get(key));
    return record ? record.value : null;
  },

  async remove(key) {
    return tx('meta', 'readwrite', (store) => store.delete(key));
  },
};
