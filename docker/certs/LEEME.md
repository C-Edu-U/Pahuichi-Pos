# Certificados HTTPS

Si en esta carpeta hay `pahuichi.crt` y `pahuichi.key`, el contenedor los usa. Si no, genera uno autofirmado y el navegador muestra una advertencia la primera vez.

Para un certificado de confianza en la red local, con [mkcert](https://github.com/FiloSottile/mkcert) (licencia BSD-3):

```bash
mkcert -install
mkcert -cert-file docker/certs/pahuichi.crt -key-file docker/certs/pahuichi.key localhost 127.0.0.1 192.168.1.50
docker compose restart app
```

Cambia `192.168.1.50` por la IP de la PC servidor. En cada celular o tablet que use el sistema hay que instalar la autoridad de mkcert (`mkcert -CAROOT` muestra dónde está).

Los certificados no se suben al repositorio.
