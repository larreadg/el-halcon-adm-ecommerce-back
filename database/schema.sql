CREATE TABLE usuario (
    id INTEGER PRIMARY KEY AUTOINCREMENT,

    usuario TEXT NOT NULL UNIQUE,
    clave_hash TEXT NOT NULL,
    activo INTEGER NOT NULL DEFAULT 1,

    creado_el TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE producto_marca (
    id INTEGER PRIMARY KEY AUTOINCREMENT,

    nombre TEXT NOT NULL UNIQUE,
    imagen TEXT,

    creado_por INTEGER,
    modificado_por INTEGER,

    creado_el TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modificado_el TEXT,

    FOREIGN KEY (creado_por) REFERENCES usuario(id),
    FOREIGN KEY (modificado_por) REFERENCES usuario(id)
);

CREATE TABLE etiqueta (
    id INTEGER PRIMARY KEY AUTOINCREMENT,

    nombre TEXT NOT NULL UNIQUE,

    creado_por INTEGER,
    modificado_por INTEGER,

    creado_el TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modificado_el TEXT,

    FOREIGN KEY (creado_por) REFERENCES usuario(id),
    FOREIGN KEY (modificado_por) REFERENCES usuario(id)
);

CREATE TABLE producto (
    id INTEGER PRIMARY KEY AUTOINCREMENT,

    nombre   TEXT NOT NULL,
    codigo   TEXT,
    descripcion TEXT,

    precio REAL NOT NULL,

    marca_id INTEGER NOT NULL,

    creado_por INTEGER,
    modificado_por INTEGER,

    creado_el TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modificado_el TEXT,

    FOREIGN KEY (marca_id) REFERENCES producto_marca(id),

    FOREIGN KEY (creado_por) REFERENCES usuario(id),
    FOREIGN KEY (modificado_por) REFERENCES usuario(id)
);

CREATE INDEX idx_producto_marca  ON producto(marca_id);
CREATE INDEX idx_producto_nombre ON producto(nombre);

CREATE TABLE producto_etiqueta (
    producto_id INTEGER NOT NULL,
    etiqueta_id INTEGER NOT NULL,
    PRIMARY KEY (producto_id, etiqueta_id),
    FOREIGN KEY (producto_id) REFERENCES producto(id) ON DELETE CASCADE,
    FOREIGN KEY (etiqueta_id) REFERENCES etiqueta(id) ON DELETE CASCADE
);

CREATE INDEX idx_producto_etiqueta_producto ON producto_etiqueta(producto_id);

CREATE TABLE producto_imagen (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    producto_id INTEGER NOT NULL,
    imagen      TEXT NOT NULL,
    orden       INTEGER NOT NULL DEFAULT 1,
    creado_por  INTEGER,
    creado_el   TEXT NOT NULL,
    FOREIGN KEY (producto_id) REFERENCES producto(id),
    FOREIGN KEY (creado_por)  REFERENCES usuario(id)
);

CREATE INDEX idx_producto_imagen_producto ON producto_imagen(producto_id);

CREATE TABLE login_intento (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    ip             TEXT NOT NULL UNIQUE,
    captcha_token  TEXT,
    captcha_valor  TEXT,
    captcha_expira TEXT
);

CREATE INDEX idx_login_intento_ip ON login_intento(ip);

CREATE TABLE descuento (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,

    nombre      TEXT NOT NULL,
    porcentaje  REAL NOT NULL,
    fecha_desde TEXT NOT NULL,
    fecha_hasta TEXT NOT NULL,

    producto_id INTEGER,
    etiqueta_id INTEGER,
    marca_id    INTEGER,

    creado_por     INTEGER,
    modificado_por INTEGER,

    creado_el   TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modificado_el TEXT,

    CHECK (
        (producto_id IS NOT NULL) + (etiqueta_id IS NOT NULL) + (marca_id IS NOT NULL) = 1
    ),

    FOREIGN KEY (producto_id) REFERENCES producto(id)      ON DELETE CASCADE,
    FOREIGN KEY (etiqueta_id) REFERENCES etiqueta(id)      ON DELETE CASCADE,
    FOREIGN KEY (marca_id)    REFERENCES producto_marca(id) ON DELETE CASCADE,
    FOREIGN KEY (creado_por)     REFERENCES usuario(id),
    FOREIGN KEY (modificado_por) REFERENCES usuario(id)
);

CREATE INDEX idx_descuento_producto ON descuento(producto_id);
CREATE INDEX idx_descuento_etiqueta ON descuento(etiqueta_id);
CREATE INDEX idx_descuento_marca    ON descuento(marca_id);
CREATE INDEX idx_descuento_fechas   ON descuento(fecha_desde, fecha_hasta);

CREATE TABLE parametro (
    clave          TEXT PRIMARY KEY,
    valor          TEXT NOT NULL DEFAULT '',
    descripcion    TEXT,
    creado_por     INTEGER,
    modificado_por INTEGER,
    creado_el      TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modificado_el  TEXT,
    FOREIGN KEY (creado_por)     REFERENCES usuario(id),
    FOREIGN KEY (modificado_por) REFERENCES usuario(id)
);

CREATE TABLE banner (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre         TEXT NOT NULL,
    descripcion    TEXT,
    imagen         TEXT,
    fecha_desde    TEXT NOT NULL,
    fecha_hasta    TEXT NOT NULL,
    creado_por     INTEGER,
    modificado_por INTEGER,
    creado_el      TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modificado_el  TEXT,
    FOREIGN KEY (creado_por)     REFERENCES usuario(id),
    FOREIGN KEY (modificado_por) REFERENCES usuario(id)
);

CREATE INDEX idx_banner_fechas ON banner(fecha_desde, fecha_hasta);
