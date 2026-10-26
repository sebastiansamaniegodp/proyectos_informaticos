document.addEventListener('alpine:init', () => {
    // Botón ☰ de la barra de arriba. En pantalla grande achica la barra
    // lateral a solo íconos (y se recuerda); en el celular la abre encima.
    window.Alpine.data('menuLateral', () => ({
        abierto: false,

        alternar() {
            if (window.matchMedia('(min-width: 64rem)').matches) {
                const mini = document.documentElement.classList.toggle('menu-mini');
                try {
                    localStorage.setItem('menu-mini', mini ? '1' : '0');
                } catch {
                    // Sin almacenamiento (modo privado): solo dura hasta recargar.
                }
            } else {
                this.abierto = !this.abierto;
            }
        },
    }));

    // Foto del producto: se achica en el navegador antes de subirla. Las fotos
    // del celular pesan varios MB y PHP acepta hasta 2 MB por archivo; así llega
    // un JPEG de 1200 px que pesa unos cientos de KB.
    window.Alpine.data('fotoProducto', () => ({
        subiendo: false,
        error: '',

        async subir(archivo) {
            if (!archivo) return;

            this.error = '';
            this.subiendo = true;

            let foto;
            try {
                foto = await achicar(archivo, 1200);
            } catch {
                this.subiendo = false;
                this.error = 'Ese archivo no es una imagen que se pueda usar.';
                return;
            }

            this.$wire.upload(
                'foto',
                foto,
                () => { this.subiendo = false; },
                () => {
                    this.subiendo = false;
                    this.error = 'No se pudo subir la foto. Probá de nuevo.';
                },
            );
        },
    }));
});

async function achicar(archivo, ladoMaximo) {
    const imagen = await createImageBitmap(archivo);
    const escala = Math.min(1, ladoMaximo / Math.max(imagen.width, imagen.height));

    const lienzo = document.createElement('canvas');
    lienzo.width = Math.round(imagen.width * escala);
    lienzo.height = Math.round(imagen.height * escala);
    lienzo.getContext('2d').drawImage(imagen, 0, 0, lienzo.width, lienzo.height);
    imagen.close();

    const blob = await new Promise((listo) => lienzo.toBlob(listo, 'image/jpeg', 0.85));
    if (!blob) throw new Error('No se pudo convertir la imagen.');

    return new File([blob], 'foto.jpg', { type: 'image/jpeg' });
}
