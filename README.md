
# Reverie Backend
Bakcend con API Restful del sistema de inventarios Reverie, enfocado en una panadería de nombre Spiral.

## Hecho
- Base de datos en mysql con tablas, vistas, procedimientos y triggers apropiados
- API restful con PHP funcionando con casi completa plenitud
- POST, PUT, GET implementados para Empleados, Productos, Ventas y Reportes
- Serivicio de ruteo apropiado con Service.PHP
- Amplio manejo de errores y validación de datos con retroalimentación positiva para el usuario
- Sistema de inicio de sesión correctamente implementado y funcional
- Revisado y probado en postman, incluyendo casos de prueba
## Documentación

Sin importar el método, todas las llamadas a la API han de ser implementadas a través de la ruta 
> /api/endpoint/service.php?service= 
>
colocar el servicio requerido después de "service="

Antes de empezar a trabajar con la API, es necesario crear la base de datos y posteriormente colocar tu username y tu password en el archivo api/config/connectiontemp.php
Posteriormente cambia el nombre del archivo a connection.php, si no, no va a funcionar nada.

Nótese que para poder llamar a los servicios es necesario iniciar sesión previamente, algunos endpoints sólo pueden ser usados por un administrador indicado por el (sólo administrador), mientras que otros pueden ser usados tanto por administradores como por empleados.
La base de datos incluye una lista de empleados ya definidos con su usario y contraseña, úsenlos para iniciar sesión, preferiblemente como administrador para evitar problemas de acceso. 
### GET
#### service=employee
(sólo administrador)
Obtener todos los empleados en la base de datos

#### service=employee&id=
(sólo administrador)
Obtener la información de un empleado con su id, la id es un número entero

#### service=currentemployee
Obtener los datos del empleado actual que haya iniciado sesión

#### service=product
Obtener todos los productos en la base de datos

#### service=product&id=
Obtener la información del producto con su código, el código es una cadena de texto (ejemplo: dnch1)

#### service=product&name=
Obtener la información del producto con su nombre, el código es una cadena de texto (ejemplo: dnch1)

#### service=sale
(sólo administrador)
Obtener las últimas 10 ventas registradas

#### service=salesbyemployee&id=
(sólo administrador)
Obtener las últimas ventas hechas por un empleado específico a través de su id (un número entero)

#### service=salesbycurrentemployee
Obtener las últimas ventas realizadas por el empleado que tenga sesión activa en ese momento

#### service=dailyreport
Obtener el reporte diario del día de hoy

#### service=dailyreport&interval=
Obtener el reporte diario dado un intervalo en días, (1, 2, 5, 10, etc)

### POST
#### service=login
Iniciar sesión en el sistema, un body es necesario al hacer la solicitud.
>ejemplo de body: 
{

    "username": "nombre de usuario",
    "password": "contraseña"
}

#### service=employee
(sólo administrador)
Agregar un empleado a la base de datos, todos los campos salvo la foto de perfil son obligatorios de enviar en el body.
>ejemplo de body:
{

    "Name": "Fernando",
    "Surname": "Fernández",
    "Username": "dobleF",
    "Password": "ffffff",
    "Shift": "Lunes-Miércoles",
    "Phone": "7711111111",
    "Photo": ""
}

#### service=product
(sólo administrador)
Agregar un producto nuevo a la base de datos, todos los datos son obligatorios.
>ejemplo de body:
{

    "Code": "cpsm1",
    "Name": "Strawberry Cupcake Medium size",
    "Description": "Medium size Cupcake strawberry flavored with strawberry pieces on top",
    "Amount": 50,
    "Price": 24,
    "Category": 3
}

#### service=sale
Añadir una venta nueva, requiere recibir un json con todos los productos que se vendieron y cuántos se vendieron, no se puede duplicar el producto dentro de una misma venta, sin un sólo producto es incorrecto, la venta no se completa.
>ejemplo de body:
{

    "Products": [
        {
            "Code": "ckch1",
            "Amount": 1
        },
        {
            "Code": "cpsm1",
            "Amount": 7
        },
        {
            "Code": "dnch1",
            "Amount": 4
        }
    ]
}

### PUT
#### service=employee&id=
(sólo administrador)
Editar la información de un empleado en específico con su id. Los datos a enviar en el body no son obligatorios, pero se debe de enviar al menos uno para que sea válida la solicitud. Los parámetros que se pueden editar son:
>['Name', 'Surname', 'Username', 'Password', 'Shift', 'Phone', 'Photo']
>ejemplo de body:
{

    "Username": "Jennyyy",
    "Password": "Liferules"
}

#### service=currentemployee
Permite editar la información del usuario que se encuentre iniciando sesión en ese momento específico. Funciona igual que el endpoint anterior, pero no requiere ninguna id.
>['Name', 'Surname', 'Username', 'Password', 'Shift', 'Phone', 'Photo']
>ejemplo de body:
{

    "Username": "Jennyyy",
    "Password": "Liferules"
}

#### service=employee&id=
(sólo administrador)
Editar la información de un producto en específico con su id. Los datos a enviar en el body no son obligatorios, pero se debe de enviar al menos uno para que sea válida la solicitud. Los parámetros que se pueden editar son:
>['Name', 'Description', 'Price', 'Category', 'Photo', 'Discontinued']

>ejemplo de body:
{

    "Price": 85
}

#### service=incproduct&id=
(sólo administrador)
Permite incrementar el stock de un producto en exactamente una unidad, sólo requiere el código del producto.

#### service=decproduct&id={}&amount={}
(sólo administrador)
Permite disminuir el stock de un producto en una cantidad determinada, se deben de pasar como parámetros el código del producto y la cantidad (número entero) a disminuir. La cantidad (amount) debe de ser menor o igual al stock existente.

### DELETE
#### service=product&id={}
(sólo administrador)
Permite hacer softdelete a los productos (modificar estado a 'discontinued') usando el endpoint creado en springboot. Los productos discontinued no se pueden vender.

#### service=employee&id={}
(sólo administrador)
Permite hacer softdelete a los empleados (modificar estado a 'discharged') usando el endpoint creado en springboot. Los empleados discharged no pueden iniciar sesión ni registrar ventas.

### PATCH
#### service=employee&id={}
Revierte el softdelete en los empleados (estado pasa a 'alive')

#### service=product&id={}
Revierte el softdelete en los productos (estado pasa a 'available')

# Reverie Frontend
GUI que interactúa con el backend y muestra un sistema de inventarios bonito y limpio, con todas las funcionalidades tanto para administradores como para empleados.

## Roles
### El administrador
- Puede crear empleados
- Puede crear productos
- Puede dar de baja a los mismos
- Puede editar a los mismos
- Puede ver resúmenes de ventas, inventario completo y lista de empleados
- Puede hacer ventas como cualquier empleado
- Puede administrar su perfil

### El empleado
- Puede registrar ventas
- Puede administrar su perfil

## Vistas

### Admin.php
Página del administrador, se le muestra un resumen de ventas y varios aparatados para ir al resto de ventanas (inventario, empleados, ventas, reportes). Tiene un apartado con advertencias de stock bajo.

### Dashboard.php
Página de ventas, permite registrar ventas agregando productos, calcula el total de compra y la registra. Muestra la lista de todos los productos. Tiene un botón para acceder al perfil personal del empleado.

### Inventory.php
Página de inventario con el listado de todos los productos, aquí se puede ver y editar su información, dar de baja y crear nuevos productos.

### Index.php
Página de inicio con un bonito banner.

### Login.php
Inicio de sesión

### MasterPage.php
Plantilla de diseño usada en el resto de vistas.

### Profile.php 
Página de perfil de usuario, permite editar la información de uno mismo.

### Sales-Report
Página con los datos de las ventas del día, con una lista de ventas y el empleado que la realizó, además de una gráfica. Periodos de 1, 7 y 30 días.

### Upload-photo.php
Servicio para subir imágenes.

====================================================
====================================================
Code made by Francisco Emmanuel Luna Hidalgo, Tolentino Segovia Luis Fernando, Arrieta Prado Isaaias Last checked 20/05/2026 
====================================================
====================================================
Instituto Tecnológico de Pachuca, Ingeniería en Sistemas Computacionales, Programación Web, proyecto final
====================================================
====================================================

{

    @@@@@@@@@@@@@@@@@@@@@%%##***++****##%%@@@@@@@@@@
    @@@@@@@@@@@@@@@@@#+++++++++++++++++++++++*#%@@@@
    @@@@@@@@@@@@@##@@%++*++++++++++++++++++++++*%@@@
    @@@@@@@@@@%*+++#@@%+%@@@#++++++++++++++++++%@@@@
    @@@@@@@@#+++++++%@@@%@@@@@@#++++++++++++++#@@@@@
    @@@@@@%*++++++++*@@@@@@@@@@@@%*++++++++++*@@@@@@
    @@@@@#+++++++++++#@@@@@@@@@@@@@@#++++++++%@@@@@@
    @@@@#+++++++++++++%@@@@@@@@@@@@@@@%+++++#@@@@@@@
    @@@#++++++++++++++#%%@@@@%#*****##%@#-:+%@@@@@@@
    @@#+++++++++++++++:=.:#++++++++++++++.=.+@@@@@@@
    @%++++++++++++++*#=.:*+++++++++++++++#+*%@@@@@@@
    @#++++++++++*#@@@#++++++++++++++++++++===+%@@@@@
    %*+++++++#@@@@@%:.-=+++++++++===+++++-.....*@@@@
    %+++++#@@@@@@@%:.....::=++++++=........-=+:.#@@@
    %+++#@@@@@@@@@+.....=.................+@%-..:@@@
    #+++*#@@@@@@@@-.  ...=@@=-+-.  .  ..:%@@-=+..#@@
    @@@@@@@@@@@@@%:.   .=@@@+..+-      .#@*-:.*..+@@
    @@@@@@@@@@@@@%-.  ..+*=*+. .*:.   .:*%@%:.*..+@@
    @@@@@@@@@@@@@@=. . .*@@@=. ..#. . .*@@@+..+..#@@
    @@@@@@@@@@@@@@#.....+@@%:. ..=.. ..%@@+..:-.:@@@
    @@@@@@@@@@@@@@@*....=@@:.    .:   .%#:...=..#@@@
    @@@@@@@@@@@@@@@@#...:=::.:.:::.....---:::....*@@
    @@@@@@@@@@@@@@@@@%-......===-.........   ..=%@@@
    @@@@@@@@@@@@@@@@@@@%=.....:=:....--......=%@@@@@
    @@@@@@@@@@@@@@@@@@@@@@#=:....:-::.....=#@@@@@@@@
    @@@@@@@@@@@@@@@@@@@@@@@@@@@%#++**#%@@@@@@@@@@@@@

}    

==============================================================================================
==============================================================================================
