<?php
session_start();

require_once 'db_connection.php';

if (!isset($_SESSION['nome_usuario'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$id_ticket = $_GET['id'];

// Selecionar os dados do ticket e o nome do solicitante
$stmt_ticket = $conn->prepare("SELECT Tickets.id, Tickets.ticket_proposta, Tickets.responsavel_id, Tickets.status, Tickets.anexo, Tickets.ticket_texto, Usuarios.nome AS nome_solicitante FROM Tickets INNER JOIN Usuarios ON Tickets.responsavel_id = Usuarios.id_user WHERE Tickets.id = ?");
$stmt_ticket->bind_param("i", $id_ticket);
$stmt_ticket->execute();
$result_ticket = $stmt_ticket->get_result();

if ($result_ticket->num_rows == 0) {
    header("Location: index.php");
    exit();
}

$stmt_ticket->close();

$sql_mensagens = "SELECT MensagensChat.mensagem, MensagensChat.data_envio, Usuarios.nome, Usuarios.id_user AS remetente_id FROM MensagensChat INNER JOIN Usuarios ON MensagensChat.remetente_id = Usuarios.id_user WHERE MensagensChat.id_ticket = ? ORDER BY MensagensChat.data_envio ASC";
$stmt_mensagens = $conn->prepare($sql_mensagens);
$stmt_mensagens->bind_param("i", $id_ticket);
$stmt_mensagens->execute();
$result_mensagens = $stmt_mensagens->get_result();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalhes do Ticket</title>
    <link href="https://fonts.googleapis.com/css?family=Montserrat:400,700" rel="stylesheet">
    <style>
        body {
            font-family: 'Montserrat', sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .container {
            background-color: #ffffff;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            padding: 20px 40px;
            max-width: 800px;
            width: 100%;
            margin-bottom: 20px;
        }
        h1 {
            color: #333;
            text-align: center;
            margin-bottom: 20px;
        }
        h2 {
            color: #005076;
            margin-top: 30px;
            margin-bottom: 10px;
        }
        ul {
            list-style-type: none;
            padding: 0;
        }
        li {
            margin-bottom: 10px;
            padding: 10px;
            border-radius: 5px;
            max-width: 70%;
            clear: both;
        }
        .cliente {
            background-color: #DCF8C6; /* Cor de fundo para mensagens do cliente */
            float: left;
        }
        .admin {
            background-color: #E5E5EA; /* Cor de fundo para mensagens do administrador */
            float: right;
        }
        .conversa {
            max-height: 400px; /* Altura máxima da lista de conversa */
            overflow-y: auto; /* Adiciona uma barra de rolagem vertical quando o conteúdo ultrapassa a altura máxima */
        }
        textarea {
            width: 100%;
            padding: 10px;
            margin-top: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            resize: vertical;
            height: 150px;
        }
        input[type="submit"] {
            background-color: #005076;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            width: 100%;
            margin-top: 10px;
        }
        input[type="submit"]:hover {
            background-color: #003b54;
        }
        button {
            background-color: #005076;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            margin-top: 10px;
        }
        button:hover {
            background-color: #003b54;
        }
        span.error-message {
            color: red;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Detalhes do Ticket</h1>
        
        <?php
        if ($row_ticket = $result_ticket->fetch_assoc()) {
            echo "<h2>Informações:</h2>";
            echo "<p>" . htmlspecialchars($row_ticket['ticket_proposta']) . "</p>";
            echo "<p>Solicitante: " . htmlspecialchars($row_ticket['nome_solicitante']) . "</p>";
            echo "<p>Status: " . htmlspecialchars($row_ticket['status']) . "</p>";
            echo "<p>Protocolo do Ticket: " . htmlspecialchars($row_ticket['id']) . "</p>";
            echo "<p>Anexos: " . (!empty($row_ticket['anexo']) ? htmlspecialchars($row_ticket['anexo']) : "Nenhum") . "</p>";
            echo "<p>Proposta:" . htmlspecialchars($row_ticket['ticket_texto']) . "</p>"; // Exibe o conteúdo de ticket_texto
        }
        ?>
        
        <div class="conversa">
            <h2>Conversa:</h2>
            <ul id="mensagens">
            <?php
            if ($result_mensagens->num_rows > 0) {
                while($row = $result_mensagens->fetch_assoc()) {
                    $classe = $row['remetente_id'] == $_SESSION['id_usuario'] ? 'cliente' : 'admin';
                    echo "<li class='$classe'>" . htmlspecialchars($row['data_envio']) . " - " . htmlspecialchars($row['nome']) . ": " . htmlspecialchars($row['mensagem']) . "</li>";
                }
            } else {
                echo "<li>Não há mensagens para este ticket.</li>";
            }
            ?>
            </ul>
        </div>
        
        <h2>Enviar Nova Mensagem:</h2>
        <form id="formMensagem">
            <label for="mensagem">Mensagem:</label><br>
            <textarea id="mensagem" name="mensagem" required></textarea><br>
            <span class="error-message" id="erro_envio"></span><br>
            <input type="submit" value="Enviar Mensagem">
        </form>
        
        <a href="index.php"><button>Voltar para a Lista de Tickets</button></a>
    </div>

    <script>
        var ws = new WebSocket('ws://localhost:8080/chat');
        var idTicket = <?php echo json_encode($id_ticket); ?>;
        var userId = <?php echo json_encode($_SESSION['id_usuario']); ?>;
        var mensagens = document.getElementById('mensagens');
        var formMensagem = document.getElementById('formMensagem');
        var textareaMensagem = document.getElementById('mensagem');
        var erroEnvio = document.getElementById('erro_envio');

        ws.onopen = function(event) {
            console.log('Conexão estabelecida.');
        };

        ws.onmessage = function(event) {
            var data = JSON.parse(event.data);
            if (data.ticket_id === idTicket) {
                var li = document.createElement('li');
                li.className = data.user_id == userId ? 'cliente' : 'admin';
                li.textContent = data.date + " - " + data.user_name + ": " + data.message;
                mensagens.appendChild(li);
            }
        };

        formMensagem.onsubmit = function(event) {
            event.preventDefault();
            var mensagem = textareaMensagem.value.trim();
            if (mensagem) {
                var data = {
                    ticket_id: idTicket,
                    message: mensagem,
                    user_id: userId
                };
                ws.send(JSON.stringify(data));
                textareaMensagem.value = '';
                erroEnvio.textContent = '';
            } else {
                erroEnvio.textContent = 'Por favor, insira uma mensagem.';
            }
        };
    </script>
</body>
</html>
