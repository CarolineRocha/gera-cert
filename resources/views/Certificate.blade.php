<!DOCTYPE html>
<html>
<head>
    <style>
        @page {
            margin: 0;
        }

        html,
        body {
            margin: 0;
            padding: 0;
        }

        .certificado{
            position:relative;
            width:1122px;
            height:793px;
            background-image:url('img/fundo-certificado.png');
            background-size:cover;
        }
.nome{
            position:absolute;
            top:318px;
            left: 367px;
            font-size:25px;
            color:white;
            font-family:Arial;
            white-space: nowrap;
        }

    </style>
</head>
<body>

    <div class="certificado">

        <div class="nome">
            {{ $nome }}
        </div>

    </div>

</body>