"""Escaneo real de ZAP, limitado al servicio de QA. No acepta una URL externa."""
import html
import json
import os
from pathlib import Path
import subprocess
import time
from html.parser import HTMLParser
import requests
import urllib3
from zapv2 import ZAPv2

BASE = 'https://app'
OUT = Path('/evidencias')
urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)

class Csrf(HTMLParser):
    def __init__(self):
        super().__init__(); self.value = ''
    def handle_starttag(self, tag, attrs):
        attrs = dict(attrs)
        if tag == 'input' and attrs.get('name') == '_csrf':
            self.value = attrs.get('value', '')

def autenticar(usuario):
    s = requests.Session()
    s.trust_env = False
    r = s.get(BASE + '/login?modo=clave', verify=False, timeout=20)
    p = Csrf(); p.feed(r.text)
    if r.status_code != 200 or not p.value:
        raise RuntimeError('No se obtuvo el token de inicio de sesión de QA.')
    r = s.post(BASE + '/login', data={'_csrf': p.value, 'modo': 'clave', 'usuario': usuario, 'clave': 'Pahuichi2026'}, verify=False, timeout=20)
    destino = '/tablero' if usuario == 'admin' else '/pos'
    if r.status_code != 200 or r.url != BASE + destino:
        raise RuntimeError('No se inició la sesión de QA: ' + usuario)
    p = Csrf(); p.feed(r.text)
    if not p.value:
        # El panel proporciona el token en body aunque no tenga formularios.
        import re
        m = re.search(r'data-csrf="([^"]+)"', r.text)
        p.value = html.unescape(m.group(1)) if m else ''
    if not p.value:
        raise RuntimeError('No se obtuvo el CSRF del panel autenticado.')
    return s, p.value, destino

def esperar(fn, limite, nombre):
    inicio = time.monotonic()
    aviso = -30
    while True:
        if fn(): return
        transcurrido = int(time.monotonic() - inicio)
        if transcurrido - aviso >= 30:
            print(nombre + ': en ejecucion (' + str(transcurrido) + ' s)', flush=True)
            aviso = transcurrido
        if time.monotonic() - inicio > limite:
            raise RuntimeError(nombre + ': tiempo agotado; el escaneo queda INCOMPLETO.')
        time.sleep(2)

def main():
    OUT.mkdir(parents=True, exist_ok=True)
    resultado = {'completo': False, 'autenticacion_verificada': False, 'alcance': BASE, 'roles': [], 'alertas': []}
    proc = None; zap = None
    try:
        caso = json.loads((OUT/'objetivo.json').read_text())
        Path('/tmp/hu66-zap').mkdir(exist_ok=True)
        log = (OUT/'zap-motor.log').open('w')
        proc = subprocess.Popen(['/zap/zap.sh', '-daemon', '-dir', '/tmp/hu66-zap', '-host', '127.0.0.1', '-port', '8090', '-config', 'api.disablekey=true'], stdout=log, stderr=subprocess.STDOUT)
        zap = ZAPv2(proxies={'http': 'http://127.0.0.1:8090', 'https': 'http://127.0.0.1:8090'})
        def listo():
            try: return bool(zap.core.version)
            except Exception: return False
        esperar(listo, 180, 'Inicio de ZAP')
        resultado['version_zap'] = zap.core.version
        contexto = zap.context.new_context('HU66')
        zap.context.include_in_context('HU66', r'https://app/.*')
        # El cierre de sesión se excluye para poder comprobar que el escáner sigue autenticado.
        zap.context.exclude_from_context('HU66', r'https://app/salir.*')
        zap.spider.set_option_process_form('false')
        zap.spider.set_option_post_form('false')
        zap.urlopen(BASE+'/login?modo=clave')
        zap.urlopen(BASE+caso['verificar'])
        for usuario in ['admin', 'mariela']:
            print('ZAP: iniciando revision autenticada de ' + usuario, flush=True)
            s, csrf, destino = autenticar(usuario)
            cookie = '; '.join(c.name+'='+c.value for c in s.cookies)
            if not cookie: raise RuntimeError('La sesión no devolvió cookie.')
            for nombre in ['hu66-cookie','hu66-csrf']:
                try: zap.replacer.remove_rule(nombre)
                except Exception: pass
            zap.replacer.add_rule('hu66-cookie', 'true', 'REQ_HEADER', 'false', 'Cookie', cookie, url=r'^https://app/.*')
            zap.replacer.add_rule('hu66-csrf', 'true', 'REQ_HEADER', 'false', 'X-CSRF-Token', csrf, url=r'^https://app/.*')
            respuesta = zap.urlopen(BASE+destino)
            if 'data-csrf=' not in respuesta or 'action="/login"' in respuesta:
                raise RuntimeError('ZAP no conserva la sesión de '+usuario)
            rutas = [destino, '/ventas', '/caja', '/catalogo/productos', '/qr',
                     '/facturas/' + str(caso['factura']),
                     '/facturas/' + str(caso['factura']) + '/pdf?seleccion=todos']
            if usuario == 'admin':
                rutas += ['/promociones', '/reportes?desde=' + time.strftime('%Y-%m-%d') + '&hasta=' + time.strftime('%Y-%m-%d'),
                          '/configuracion', '/conciliacion', '/usuarios', '/clientes']
            for ruta in rutas:
                zap.urlopen(BASE + ruta)
            # Semillas POST de validacion, a traves del proxy. El escaneo activo puede
            # mutarlas: solo alcanza la base desechable de QA y usa el CSRF del rol.
            sesion_proxy = requests.Session()
            sesion_proxy.trust_env = False
            sesion_proxy.proxies = {'http': 'http://127.0.0.1:8090', 'https': 'http://127.0.0.1:8090'}
            sesion_proxy.headers.update({'Cookie': cookie, 'X-CSRF-Token': csrf, 'Accept': 'application/json'})
            sesion_proxy.post(BASE + '/pos/cobrar', data={'lineas': '[]', 'caja_id': str(caso['caja'])}, verify=False, timeout=30)
            if usuario == 'admin':
                sesion_proxy.post(BASE + '/catalogo/productos/nuevo', data={'codigo': '', 'nombre': '', 'precio': ''}, verify=False, timeout=30)
            spider = zap.spider.scan(BASE+destino, recurse=True, contextname='HU66')
            esperar(lambda: int(zap.spider.status(spider)) == 100, 300, 'Spider '+usuario)
            if not zap.ascan.scanners(): raise RuntimeError('ZAP no tiene reglas de escaneo activo.')
            scan = zap.ascan.scan(BASE, recurse=True, inscopeonly=True)
            esperar(lambda: int(zap.ascan.status(scan)) == 100, 900, 'Escaneo activo '+usuario)
            respuesta = zap.urlopen(BASE+destino)
            if 'data-csrf=' not in respuesta or 'action="/login"' in respuesta:
                raise RuntimeError('Se perdió la sesión durante el escaneo de '+usuario)
            resultado['roles'].append({'usuario_qa': usuario, 'sesion_inicio_y_fin': True, 'spider_id': spider, 'escaneo_activo_id': scan})
            print('ZAP: revision finalizada de ' + usuario, flush=True)
        esperar(lambda: int(zap.pscan.records_to_scan) == 0, 180, 'Análisis pasivo')
        resultado['urls'] = zap.core.urls(baseurl=BASE)
        if not resultado['urls']: raise RuntimeError('ZAP no registró URLs analizadas.')
        riesgos = {'Informational':0,'Low':1,'Medium':2,'High':3}
        inicio = 0
        while True:
            lote = zap.core.alerts(baseurl=BASE, start=inicio, count=1000)
            for alerta in lote:
                if alerta.get('risk') not in riesgos: raise RuntimeError('Riesgo de ZAP desconocido; requiere revisión.')
                alerta['riskcode'] = riesgos[alerta['risk']]
                resultado['alertas'].append(alerta)
            inicio += len(lote)
            if len(lote) < 1000: break
            if inicio > 100000: raise RuntimeError('Demasiadas alertas; revisión manual necesaria, resultado incompleto.')
        resultado['completo'] = True
        resultado['autenticacion_verificada'] = len(resultado['roles']) == 2
        # Reporte original del motor, además del JSON usado por el evaluador.
        (OUT/'zap-original.html').write_text(zap.core.htmlreport(), encoding='utf-8')
    except Exception as error:
        resultado['error'] = str(error)
        print('ZAP PENDIENTE:', error, flush=True)
    finally:
        (OUT/'zap.json').write_text(json.dumps(resultado, ensure_ascii=False, indent=2), encoding='utf-8')
        if zap:
            try: zap.core.shutdown()
            except Exception: pass
        if proc:
            try: proc.wait(timeout=30)
            except subprocess.TimeoutExpired: proc.kill()
    altas = sum(a['riskcode'] >= 3 for a in resultado['alertas'])
    print('ZAP: completo=', resultado['completo'], 'alertas altas=', altas, flush=True)
    return 0 if resultado['completo'] and resultado['autenticacion_verificada'] and altas == 0 else 1

if __name__ == '__main__':
    raise SystemExit(main())
