<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
$perPageOptions = [20, 50, 100, 200, 500, 800]; // 可选值
$perPage = isset($_GET['per_page']) && in_array((int)$_GET['per_page'], $perPageOptions) 
         ? (int)$_GET['per_page'] 
         : 50; // 默认值

// 数据库配置
$dbHost = ''; // 连接信息
$dbName = ''; // 数据库名
$dbUser = ''; // 用户名
$dbPass = ''; // 密码
$tableName = 'scn'; // 表名
$baseDir = 'advvoice'; // 语音目录

// +++++++++++++++++++++++ 语言配置开始 +++++++++++++++++++++++
// 语言检测
$lang = isset($_GET['lang']) && $_GET['lang'] === 'en' ? 'en' : 'zh';

// 翻译字典
$translations = [
    'zh' => [
        'title' => 'GA2剧情台词浏览器',
        'search_placeholder' => [
            'sentence' => '搜索台词...',
            'speaker' => '搜索说话人...',
            'id' => '搜索ID...',
        ],
        'search_button' => '搜索',
        'clear_button' => '清除',
        'no_results' => '没有找到匹配的结果',
        'search_hint' => '长按搜索结果可跳转至该条所在分页',
        'sentence_tab' => '台词',
        'speaker_tab' => '说话人'
    ],
    'en' => [
        'title' => 'GA2 Sentence View',
        'search_placeholder' => [
            'sentence' => 'Search sentence...',
            'speaker' => 'Search speaker...',
            'id' => 'Search ID...'
        ],
        'search_button' => 'Search',
        'clear_button' => 'Clear',
        'no_results' => 'No matching results found',
        'search_hint' => 'Long press search result to jump to its page',
        'sentence_tab' => 'Sentence',
        'speaker_tab' => 'Speaker',
    ]
];
$t = $translations[$lang];
// +++++++++++++++++++++++ 语言配置结束 +++++++++++++++++++++++

try {
    // 连接数据库
    $db = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("数据库连接失败: " . $e->getMessage());
}

// 处理搜索参数
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
$searchType = isset($_GET['type']) && in_array($_GET['type'], ['sentence','speaker','id']) 
            ? $_GET['type'] 
            : 'sentence';

// 分页参数
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$where = '';
$params = [];
if ($searchTerm !== '') {
    // 直接去除搜索词中的空白字符（简单处理）
    $cleanSearchTerm = preg_replace('/\s+/', '', $searchTerm);
    // 如果是搜索说话人，并且输入是纯数字，则精确匹配
    if ($searchType === 'speaker' && ctype_digit($cleanSearchTerm)) {
        $where = " WHERE $searchType = ?";
        $params[] = $cleanSearchTerm;
    } 
    // 其他情况（非纯数字的 speaker 搜索，或者 sentence/id 搜索）保持原逻辑
    else {
        $params[] = "%$cleanSearchTerm%";
        $where = " WHERE REPLACE(REPLACE($searchType, ' ', ''), '\n', '') LIKE ?";
    }
}
// 查询当前页数据
$sql = "SELECT * FROM $tableName $where ORDER BY id LIMIT $perPage OFFSET $offset";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$filteredSentences = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 查询总数
$countSql = "SELECT COUNT(*) FROM $tableName $where";
$countStmt = $db->prepare($countSql);
$countStmt->execute($params);
$total = $countStmt->fetchColumn();
$totalPages = ceil($total / $perPage);

// 处理音频播放
if (isset($_GET['play'])) {
    $playId = (int)$_GET['play'];
    $stmt = $db->prepare("SELECT audio FROM $tableName WHERE id = ?");
    $stmt->execute([$playId]);
    $audioFile = strtolower($stmt->fetchColumn());
    
    if ($audioFile && file_exists($baseDir . DIRECTORY_SEPARATOR . $audioFile)) {
        header('Content-Type: audio/wave');
        readfile($baseDir . DIRECTORY_SEPARATOR . $audioFile);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $lang === 'en' ? 'en' : 'zh-CN' ?>"> <!-- 修改html lang属性 -->
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $t['title'] ?></title> <!-- 标题国际化 -->
    <style>
        /* 保持原有完整CSS不变 */
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            padding-bottom: 70px;
        }
        .search-box {
            margin-bottom: 20px;
            padding: 15px;
            background-color: #f5f5f5;
            border-radius: 8px;
        }
        .search-row {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
        }
        input[type="text"] {
            flex: 1;
            padding: 8px 12px;
            border: 2px solid #ddd;
            border-radius: 4px;
        }
        button[type="submit"] {
            padding: 8px 20px;
            background: #2196F3;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .search-types {
            display: flex;
            gap: 8px;
        }
        .type-btn {
            padding: 6px 15px;
            border: 2px solid #2196F3;
            border-radius: 15px;
            background: white;
            color: #2196F3;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 0.9em;
        }
        .type-btn.active {
            background: #2196F3;
            color: white;
        }
        .sentence-list {
            list-style: none;
            padding: 0;
        }
        .sentence-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 12px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
            transition: background 0.2s;
            -webkit-touch-callout: none;
            -webkit-user-select: none;
            user-select: none;
        }
        .sentence-item:hover {
            background-color: #f8f8f8;
        }
        .info-block {
            flex: 0 0 90px;
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        .speaker-badge, .hash-badge {
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 0.75em;
            line-height: 1.3;
        }
        .speaker-badge {
            background: #2196F3;
            color: white;
        }
        .hash-badge {
            background: #4CAF50;
            color: white;
            word-break: break-all;
        }
        .sentence-text {
            flex-grow: 1;
            font-size: 0.95em;
        }
        .audio-player {
            display: none;
        }
        .no-results {
            color: #666;
            padding: 20px;
            text-align: center;
        }
        .clear-btn {
            padding: 8px 15px;
            background: #eee;
            color: #666;
            text-decoration: none;
            border-radius: 4px;
        }
        .pagination {
            margin: 20px 0;
            text-align: center;
        }
        .pagination a {
            display: inline-block;
            padding: 8px 12px;
            margin: 0 3px;
            background: #f0f0f0;
            border-radius: 4px;
            text-decoration: none;
        }
        .pagination a.active {
            background: #2196F3;
            color: white;
        }
        .search-hint {
            font-size: 0.8em;
            color: #666;
            text-align: center;
            margin: 10px 0;
            opacity: 0.8;
        }
        .page-selector {
            padding: 8px 12px;
            border: 2px solid #2196F3;
            border-radius: 4px;
            background: white;
            color: #2196F3;
            font-size: 1em;
            cursor: pointer;
            outline: none;
            width: 150px;
            text-align: center;
        }

        .page-selector:hover {
            background: #f5f5f5;
        }

        .page-selector option {
            padding: 5px;
        }
    </style>
</head>
<body>
    <h1><?= $t['title'] ?></h1> <!-- 标题国际化 -->
    
    <div class="search-box">
        <form method="get" action="">
            <input type="hidden" name="lang" value="<?= $lang ?>"> <!-- 语言参数 -->
            <div class="search-row">
                <input type="text" name="search" 
                       placeholder="<?= $t['search_placeholder'][$searchType] ?>"
                       value="<?= htmlspecialchars($searchTerm) ?>">
                <button type="submit"><?= $t['search_button'] ?></button> <!-- 按钮文字国际化 -->
                <?php if (!empty($searchTerm)): ?>
                    <a href="?lang=<?= $lang ?>" class="clear-btn"><?= $t['clear_button'] ?></a> <!-- 清除按钮带语言参数 -->
                <?php endif; ?>
            </div>
            <div class="search-types">
                <div class="type-btn <?= $searchType === 'sentence' ? 'active' : '' ?>" 
                     onclick="setSearchType('sentence')"><?= $t['sentence_tab'] ?></div> <!-- 标签文字国际化 -->
                <div class="type-btn <?= $searchType === 'speaker' ? 'active' : '' ?>" 
                     onclick="setSearchType('speaker')"><?= $t['speaker_tab'] ?></div> <!-- 标签文字国际化 -->
                <div class="type-btn <?= $searchType === 'id' ? 'active' : '' ?>" 
                     onclick="setSearchType('id')">ID</div>
                <input type="hidden" name="type" id="searchType" value="<?= $searchType ?>">
            </div>
        </form>
    </div>

    <?php if (!empty($searchTerm)): ?>
        <div class="search-hint"><?= $t['search_hint'] ?></div> <!-- 搜索提示国际化 -->
    <?php endif; ?>

    <ul class="sentence-list">
        <?php if (empty($filteredSentences)): ?>
            <li class="no-results"><?= $t['no_results'] ?></li> <!-- 无结果提示国际化 -->
        <?php else: ?>
            <?php foreach ($filteredSentences as $entry): ?>
                <li class="sentence-item" data-id="<?= $entry['id'] ?>">
                    <div class="info-block">
                        <?php if (!empty($entry['speaker'])): ?>
                            <div class="speaker-badge"><?= htmlspecialchars($entry['speaker']) ?></div>
                        <?php endif; ?>
                        <div class="hash-badge"><?= htmlspecialchars($entry['id']) ?></div>
                    </div>
                    <div class="sentence-text"><?= htmlspecialchars($entry['sentence']) ?></div>
                </li>
            <?php endforeach; ?>
        <?php endif; ?>
    </ul>

<?php if ($totalPages > 1): ?>
    <div class="pagination" style="
    position: fixed; 
    bottom: 0; 
    left: 0; 
    right: 0; 
    background: white; 
    padding: 10px 0; 
    box-shadow: 0 -2px 10px rgba(0,0,0,0.1); 
    z-index: 1000; 
    display: flex; 
    align-items: center; 
    justify-content: center; 
    gap: 10px;
    margin: 0;
">

    <!-- 上一页按钮 -->
    <button onclick="goToPage(<?= $page - 1 ?>)" 
            <?= $page <= 1 ? 'disabled' : '' ?>
            class="page-button" title="Previous">
        &lt;
    </button>
    
    <!-- 分页选择器 -->
    <select onchange="window.location.href=this.value" class="page-selector">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <option value="?lang=<?= $lang ?>&search=<?= urlencode($searchTerm) ?>&type=<?= $searchType ?>&page=<?= $i ?>&per_page=<?= $perPage ?>"
                <?= $i == $page ? 'selected' : '' ?>>
                <?= $i ?>/<?= $totalPages ?>
            </option>
        <?php endfor; ?>
    </select>
    
    <!-- 下一页按钮 -->
    <button onclick="goToPage(<?= $page + 1 ?>)" 
            <?= $page >= $totalPages ? 'disabled' : '' ?>
            class="page-button" title="Next">
        &gt;
    </button>
    <!-- 在下一页按钮后面添加这个 -->
    <select onchange="updatePerPage(this.value)" 
            style="padding: 4px 8px; height: 34px; border-radius: 4px; border: 1px solid #ddd;"
            title="Items per page">
        <?php foreach ($perPageOptions as $option): ?>
            <option value="<?= $option ?>" <?= $option == $perPage ? 'selected' : '' ?>>
                <?= $option ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>

<script>
// 跳转到指定页面的函数
function goToPage(pageNum) {
    const url = new URL(window.location.href);
    url.searchParams.set('page', Math.max(1, Math.min(pageNum, <?= $totalPages ?>)));
    window.location.href = url.toString();
}
</script>

<style>
.page-button {
    padding: 8px 12px;
    background: #f0f0f0;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 16px;
    transition: all 0.3s;
}

.page-button:hover:not(:disabled) {
    background: #2196F3;
    color: white;
}

.page-button:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
</style>
<?php endif; ?>

    <audio id="audioPlayer" class="audio-player" controls></audio>

    <script>
        function updatePerPage(value) {
            const url = new URL(window.location.href);
            url.searchParams.set('per_page', value);
            url.searchParams.set('page', 1); // 重置到第一页
            window.location.href = url.toString();
        }        
        // 搜索类型切换（增加国际化支持）
        function setSearchType(type) {
            document.getElementById('searchType').value = type;
            document.querySelectorAll('.type-btn').forEach(btn => {
                btn.classList.remove('active');
                if (btn.getAttribute('onclick').includes(type)) {
                    btn.classList.add('active');
                }
            });
            const placeholderMap = {
                sentence: '<?= $t['search_placeholder']['sentence'] ?>',
                speaker: '<?= $t['search_placeholder']['speaker'] ?>',
                id: '<?= $t['search_placeholder']['id'] ?>'
            };
            document.querySelector('input[name="search"]').placeholder = placeholderMap[type];
        }

        // 以下JavaScript代码完全保持不变
        // 音频播放处理
        function playAudio(id) {
            const player = document.getElementById('audioPlayer');
            player.src = `?play=${id}`;
            player.play().catch(error => {
                player.muted = true;
                player.play().then(() => {
                    player.muted = false;
                });
            });
        }

        // 智能事件处理
        document.querySelectorAll('.sentence-item').forEach(item => {
            const id = item.dataset.id;
            let pressTimer;
            let startY;
            let isScrolling = false;

            item.addEventListener('touchstart', function(e) {
                if (e.touches.length !== 1) return;
                startY = e.touches[0].clientY;
                isScrolling = false;
                pressTimer = setTimeout(() => {
                    handleLongPress(id);
                }, 600);
            }, { passive: true });

            item.addEventListener('touchmove', function(e) {
                if (!pressTimer) return;
                const currentY = e.touches[0].clientY;
                if (Math.abs(currentY - startY) > 5) {
                    clearTimeout(pressTimer);
                    pressTimer = null;
                    isScrolling = true;
                }
            }, { passive: true });

            item.addEventListener('touchend', function(e) {
                if (pressTimer) {
                    clearTimeout(pressTimer);
                    if (!isScrolling) {
                        playAudio(id);
                    }
                }
                pressTimer = null;
                isScrolling = false;
            });

            let mouseTimer;
            item.addEventListener('mousedown', function() {
                mouseTimer = setTimeout(() => {
                    handleLongPress(id);
                }, 600);
            });

            item.addEventListener('mouseup', function() {
                clearTimeout(mouseTimer);
            });

            item.addEventListener('mouseleave', function() {
                clearTimeout(mouseTimer);
            });

            item.addEventListener('click', function() {
                clearTimeout(mouseTimer);
                playAudio(id);
            });
        });

        // 长按跳转处理
    function handleLongPress(id) {
        const targetPage = Math.ceil(id / <?= $perPage ?>);
        window.location.href = `?lang=<?= $lang ?>&page=${targetPage}&per_page=<?= $perPage ?>`;
    }
    </script>
</body>
</html>