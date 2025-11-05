<?php
// Настройка времени и лимита
$cooldownTime = 15 * 60; // 15 минут в секундах
$gridSize = 1000; // Размер сетки 1000x1000

// Пути к файлам
$gridFile = 'grid.json';
$ipLogFile = 'ipLog.json';

// Загрузка состояния сетки
if (file_exists($gridFile)) {
    $grid = json_decode(file_get_contents($gridFile), true);
} else {
    $grid = array_fill(0, $gridSize * $gridSize, '#FFFFFF'); // Инициализация белой сетки
}

// Загрузка логов IP
if (file_exists($ipLogFile)) {
    $ipLog = json_decode(file_get_contents($ipLogFile), true);
} else {
    $ipLog = [];
}

// Получение IP пользователя
$userIp = $_SERVER['REMOTE_ADDR'];

// Обработка запроса на покраску пикселя
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $index = intval($_POST['index']);
    $color = trim($_POST['color']);

    // Валидация индекса
    if ($index < 0 || $index >= count($grid)) {
        echo json_encode(['success' => false, 'message' => 'Неверный индекс пикселя.']);
        exit;
    }

    // Валидация цвета
    if (!preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $color)) {
        echo json_encode(['success' => false, 'message' => 'Неверный формат цвета.']);
        exit;
    }

    // Проверка таймера
    $currentTime = time();
    if (isset($ipLog[$userIp]) && $currentTime - $ipLog[$userIp] < $cooldownTime) {
        echo json_encode(['success' => false, 'message' => 'Подождите 15 минут перед следующим действием.']);
        exit;
    }

    // Обновление данных
    $grid[$index] = $color;
    $ipLog[$userIp] = $currentTime;

    file_put_contents($gridFile, json_encode($grid));
    file_put_contents($ipLogFile, json_encode($ipLog));

    echo json_encode(['success' => true]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pixel Art</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f0f8ff;
            color: #000;
            text-align: center;
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(100, 1fr); /* 1000/10 = 100 колонок */
            grid-gap: 1px;
            justify-content: center;
            margin-top: 20px;
        }
        .pixel {
            width: 10px;
            height: 10px;
            background-color: #fff;
            border: 1px solid #ccc;
            cursor: pointer;
        }
        .cooldown {
            margin-top: 20px;
            font-size: 18px;
        }
        .tools {
            margin-bottom: 20px;
        }
        .tools button {
            padding: 10px 20px;
            margin: 5px;
            font-size: 16px;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <h1>Pixel Art</h1>

    <!-- Панель инструментов -->
    <div class="tools">
        <label for="colorPicker">Выберите цвет:</label>
        <input type="color" id="colorPicker" value="#ffffff">

        <button id="eyedropperButton">Использовать пипетку</button>
    </div>

    <div class="grid" id="pixelGrid"></div>
    <div class="cooldown" id="cooldownMessage"></div>

    <script>
        const gridSize = 1000; // Размер сетки 1000x1000
        let cooldown = false;

        // Создание сетки
        function createGrid() {
            const gridElement = document.getElementById('pixelGrid');
            <?php foreach ($grid as $index => $color): ?>
                const pixel = document.createElement('div');
                pixel.classList.add('pixel');
                pixel.style.backgroundColor = '<?php echo $color; ?>';
                pixel.dataset.index = <?php echo $index; ?>;
                pixel.addEventListener('click', handlePixelClick);
                gridElement.appendChild(pixel);
            <?php endforeach; ?>
        }

        // Обработка клика по пикселю
        function handlePixelClick(event) {
            if (cooldown) {
                alert('Подождите 15 минут перед следующим действием.');
                return;
            }

            const index = event.target.dataset.index;
            const color = document.getElementById('colorPicker').value;

            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `index=${index}&color=${encodeURIComponent(color)}`
            }).then(response => response.json())
              .then(data => {
                  if (data.success) {
                      event.target.style.backgroundColor = color;
                      startCooldown();
                  } else {
                      alert('Ошибка: ' + data.message);
                  }
              });
        }

        // Запуск таймера ожидания
        function startCooldown() {
            cooldown = true;
            document.getElementById('cooldownMessage').textContent = 'Подождите 15 минут...';
            setTimeout(() => {
                cooldown = false;
                document.getElementById('cooldownMessage').textContent = '';
            }, 15 * 60 * 1000); // 15 минут
        }

        // Обработка пипетки
        document.getElementById('eyedropperButton').addEventListener('click', async () => {
            if (!window.EyeDropper) {
                alert('Ваш браузер не поддерживает пипетку. Попробуйте использовать Chrome.');
                return;
            }

            const eyeDropper = new EyeDropper();
            try {
                const result = await eyeDropper.open();
                document.getElementById('colorPicker').value = result.sRGBHex;
            } catch (error) {
                console.error('Ошибка при использовании пипетки:', error);
            }
        });

        // Инициализация
        createGrid();
    </script>
</body>
</html>
