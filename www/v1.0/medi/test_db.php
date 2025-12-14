<?php
/**
 * 데이터베이스 연결 테스트
 * 이 파일로 먼저 DB 연결을 확인하세요.
 * 
 * 실행 방법: 브라우저에서 test_db.php 접속
 */

require_once '/../../lib/MediConfig.php';

?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>데이터베이스 연결 테스트</title>
    <style>
        body {
            font-family: 'Malgun Gothic', sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #4CAF50;
            padding-bottom: 10px;
        }
        .success {
            color: #4CAF50;
            background: #e8f5e9;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }
        .error {
            color: #f44336;
            background: #ffebee;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }
        .info {
            color: #2196F3;
            background: #e3f2fd;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #4CAF50;
            color: white;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: #4CAF50;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 20px;
        }
        .btn:hover {
            background-color: #45a049;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔌 데이터베이스 연결 테스트</h1>
        
        <?php
        // 1. 연결 테스트
        echo "<h2>1️⃣ 연결 테스트</h2>";
        try {
            $pdo = getDBConnection();
            echo "<div class='success'>";
            echo "✅ <strong>데이터베이스 연결 성공!</strong><br>";
            echo "Host: " . DB_HOST . "<br>";
            echo "Database: " . DB_NAME . "<br>";
            echo "User: " . DB_USER;
            echo "</div>";
            
            // 2. MySQL 버전 확인
            echo "<h2>2️⃣ MySQL 서버 정보</h2>";
            $version = $pdo->query('SELECT VERSION()')->fetchColumn();
            echo "<div class='info'>";
            echo "MySQL Version: <strong>" . $version . "</strong>";
            echo "</div>";
            
            // 3. 현재 데이터베이스 확인
            echo "<h2>3️⃣ 현재 데이터베이스</h2>";
            $current_db = $pdo->query('SELECT DATABASE()')->fetchColumn();
            echo "<div class='info'>";
            echo "사용 중인 데이터베이스: <strong>" . ($current_db ?: '없음') . "</strong>";
            echo "</div>";
            
            // 4. 테이블 목록 확인
            echo "<h2>4️⃣ 테이블 목록</h2>";
            $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            
            if (count($tables) > 0) {
                echo "<table>";
                echo "<tr><th>번호</th><th>테이블 이름</th><th>상태</th></tr>";
                foreach ($tables as $index => $table) {
                    echo "<tr>";
                    echo "<td>" . ($index + 1) . "</td>";
                    echo "<td>" . $table . "</td>";
                    echo "<td><span style='color: #4CAF50;'>✓ 존재</span></td>";
                    echo "</tr>";
                }
                echo "</table>";
            } else {
                echo "<div class='info'>";
                echo "⚠️ 아직 생성된 테이블이 없습니다.<br>";
                echo "아래 버튼을 클릭하여 테이블을 생성하세요.";
                echo "</div>";
            }
            
            // 5. 권한 확인
            echo "<h2>5️⃣ 데이터베이스 권한 테스트</h2>";
            $permissions = [];
            
            // SELECT 권한
            try {
                $pdo->query("SELECT 1");
                $permissions['SELECT'] = true;
            } catch (Exception $e) {
                $permissions['SELECT'] = false;
            }
            
            // INSERT 권한 (임시 테이블로 테스트)
            try {
                $pdo->exec("CREATE TEMPORARY TABLE IF NOT EXISTS test_table (id INT)");
                $pdo->exec("INSERT INTO test_table VALUES (1)");
                $permissions['INSERT'] = true;
                $pdo->exec("DROP TEMPORARY TABLE IF EXISTS test_table");
            } catch (Exception $e) {
                $permissions['INSERT'] = false;
            }
            
            echo "<table>";
            echo "<tr><th>권한</th><th>상태</th></tr>";
            foreach ($permissions as $perm => $status) {
                echo "<tr>";
                echo "<td>" . $perm . "</td>";
                echo "<td>" . ($status ? "<span style='color: #4CAF50;'>✓ 사용 가능</span>" : "<span style='color: #f44336;'>✗ 사용 불가</span>") . "</td>";
                echo "</tr>";
            }
            echo "</table>";
            
            // 다음 단계 안내
            echo "<hr>";
            echo "<h2>📋 다음 단계</h2>";
            
            if (count($tables) == 0) {
                echo "<div class='info'>";
                echo "<p><strong>테이블이 없습니다. 다음 단계를 진행하세요:</strong></p>";
                echo "<ol>";
                echo "<li>아래 '테이블 생성하기' 버튼 클릭</li>";
                echo "<li>모든 테이블이 생성되면 개발 시작</li>";
                echo "</ol>";
                echo "</div>";
                echo "<a href='setup_database.php' class='btn'>🔧 테이블 생성하기</a>";
            } else {
                echo "<div class='success'>";
                echo "<p><strong>✅ 모든 준비가 완료되었습니다!</strong></p>";
                echo "<p>이제 개발을 시작할 수 있습니다.</p>";
                echo "</div>";
                echo "<a href='index.php' class='btn'>🏠 메인 페이지로 이동</a>";
            }
            
        } catch (Exception $e) {
            echo "<div class='error'>";
            echo "❌ <strong>데이터베이스 연결 실패</strong><br><br>";
            echo "<strong>에러 메시지:</strong><br>";
            echo $e->getMessage();
            echo "<br><br>";
            echo "<strong>해결 방법:</strong><br>";
            echo "<ol>";
            echo "<li>RDS 인스턴스가 실행 중인지 확인</li>";
            echo "<li>보안 그룹에서 현재 IP가 허용되어 있는지 확인</li>";
            echo "<li>DB 이름(telemedicine)이 존재하는지 확인</li>";
            echo "<li>아이디/비밀번호가 정확한지 확인</li>";
            echo "</ol>";
            echo "</div>";
        }
        ?>
        
        <hr>
        <p style="color: #999; font-size: 12px;">
            ⚠️ 보안 주의: 이 파일은 배포 전에 삭제하거나 접근을 제한하세요.
        </p>
    </div>
</body>
</html>