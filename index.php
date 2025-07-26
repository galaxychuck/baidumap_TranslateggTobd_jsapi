<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8" />
  <title>地图坐标提取器</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <style>
    body {
      font-family: Arial, sans-serif;
      margin: 0;
      background-color: #f5f5f5;
    }

    #map {
      width: 100%;
      height: 750px;
      position: relative;
      z-index: 1;
    }

    .controls {
      position: absolute;
      top: 10px;
      left: 10%;
      transform: translateX(-50%);
      background: rgba(255, 255, 255, 0.7);
      padding: 8px 16px;
      border-radius: 10px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
      z-index: 1000;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 6px;
      width: 320px;
      font-size: 14px;
    }

    .controls .title {
      font-weight: 600;
      font-size: 1.2em;
      margin: 0;
      user-select: none;
    }

    input[type="text"] {
      width: 100%;
      padding: 6px 8px;
      border: 1px solid #ccc;
      border-radius: 6px;
      box-sizing: border-box;
      font-size: 14px;
    }

    button {
      width: 100%;
      padding: 6px 0;
      background-color: #2196f3;
      border-radius: 6px;
      border: none;
      color: white;
      cursor: pointer;
      font-size: 14px;
    }

    button:hover {
      background-color: #0b7dda;
    }

    #popup {
      position: absolute;
      top: 165px;
      left: 15px;
      background: white;
      padding: 15px 50px 15px 15px; /* 右边留50px给关闭按钮 */
      border-radius: 10px;
      box-shadow: 0 2px 12px rgba(0, 0, 0, 0.2);
      display: none;
      z-index: 1000;
      max-width: 320px;
      word-wrap: break-word;
      box-sizing: border-box;
      overflow: visible;
    }

    #popup-close {
      position: absolute;
      top: 8px;
      right: 8px;
      z-index: 10; /* 确保按钮在最上层 */
      background: none;
      border: none;
      font-size: 20px;
      line-height: 20px;
      cursor: pointer;
      color: #666;
      font-weight: bold;
      user-select: none;
      padding: 0;
      margin: 0;
      width: 24px;
      height: 24px;
      text-align: center;
      line-height: 24px;
    }

    #record-table {
      width: 100%;
      margin-top: 1em;
      border-collapse: collapse;
    }

    #record-table th,
    #record-table td {
      border: 1px solid #ccc;
      padding: 8px;
    }

    #record-table th {
      background-color: #eee;
    }

    /* 复制提示弹窗 */
    #copy-notice {
      position: fixed;
      bottom: 20px;
      left: 50%;
      transform: translateX(-50%);
      background: rgba(0, 0, 0, 0.7);
      color: white;
      padding: 10px 20px;
      border-radius: 20px;
      font-size: 14px;
      display: none;
      z-index: 2000;
      user-select: none;
      display: flex;
      align-items: center;
      gap: 10px;
      max-width: 80%;
      box-sizing: border-box;
    }

    #copy-notice button {
      border: none;
      background: none;
      color: #fff;
      cursor: pointer;
      font-size: 18px;
      font-weight: bold;
      line-height: 1;
      user-select: none;
    }

    /* 移动端适配 */
    @media screen and (max-width: 600px) {
      .controls {
        left: 50%;
        transform: translateX(-50%);
        width: 90%;
        padding: 10px;
        font-size: 16px;
      }

      input[type="text"],
      button {
        font-size: 16px;
        padding: 10px;
      }

      #map {
        height: 70vh;
      }

      #popup {
        width: 90%;
        left: 2%;
        top: auto;
        bottom: 20px;
        padding-right: 40px;
      }

      #record-table th,
      #record-table td {
        font-size: 12px;
        padding: 6px;
      }

      .controls .title {
        font-size: 1.4em;
      }
    }
  </style>
  <script src="https://api.map.baidu.com/api?v=1.0&type=webgl&ak=16c0e92f74d3af8e0a9c729bde0339cb"></script>
</head>
<body>
  <div class="controls">
    <div class="title">地图坐标提取器</div>
    <input type="text" id="searchInput" placeholder="请输入坐标或城市名称" />
    <button onclick="search()">查询</button>
    <button onclick="getCurrentLocation()">获取当前位置</button>
  </div>

  <div id="popup">
    <button id="popup-close" title="关闭">×</button>
  </div>

  <div
    id="copy-notice"
    role="alert"
    aria-live="assertive"
    aria-atomic="true"
    style="display: none;"
  >
    <span id="copy-notice-text">已复制</span>
    <button id="copy-notice-close" title="关闭">×</button>
  </div>

  <div id="map"></div>

  <table id="record-table">
    <thead>
      <tr>
        <th>时间</th>
        <th>经度</th>
        <th>纬度</th>
        <th>地址</th>
      </tr>
    </thead>
    <tbody id="record-body"></tbody>
  </table>

  <script>
    const map = new BMapGL.Map("map");
    map.centerAndZoom(new BMapGL.Point(116.660646, 26.268447), 12);
    map.enableScrollWheelZoom(true);
    const geocoder = new BMapGL.Geocoder();

    function showPopup(lng, lat, address) {
      const popup = document.getElementById("popup");
      popup.innerHTML = `
        <button id="popup-close" title="关闭">×</button>
        <b>坐标：</b>${lng}, ${lat}<br>
        <b>地址：</b>${address}<br><br>
        <button onclick="copyCoords('${lng},${lat}')">复制坐标</button>
      `;
      popup.style.display = "block";

      // 重新绑定关闭按钮事件（因为重新innerHTML后事件失效）
      document
        .getElementById("popup-close")
        .addEventListener("click", function () {
          popup.style.display = "none";
        });
    }

    function copyCoords(text) {
      navigator.clipboard.writeText(text).then(() => {
        const notice = document.getElementById("copy-notice");
        document.getElementById("copy-notice-text").textContent = "已复制: " + text;
        notice.style.display = "flex";

        // 自动3秒后隐藏
        setTimeout(() => {
          notice.style.display = "none";
        }, 3000);
      });
    }

    // 复制提示关闭按钮事件
    document
      .getElementById("copy-notice-close")
      .addEventListener("click", function () {
        document.getElementById("copy-notice").style.display = "none";
      });

    // 初始绑定 popup 关闭按钮事件（首次页面加载可能没内容，后续showPopup会重新绑定）
    const initialPopupClose = document.getElementById("popup-close");
    if (initialPopupClose) {
      initialPopupClose.addEventListener("click", function () {
        document.getElementById("popup").style.display = "none";
      });
    }

    function addRecord(lng, lat, address) {
      const tbody = document.getElementById("record-body");
      const tr = document.createElement("tr");
      const now = new Date().toLocaleString();
      tr.innerHTML = `<td>${now}</td><td>${lng}</td><td>${lat}</td><td>${address}</td>`;
      tbody.prepend(tr);
    }

    function search() {
      const input = document.getElementById("searchInput").value.trim();
      if (!input) return;

      if (/^\d+(\.\d+)?[,，]\d+(\.\d+)?$/.test(input)) {
        const parts = input.split(/[,，]/);
        const lng = parseFloat(parts[0]);
        const lat = parseFloat(parts[1]);
        const point = new BMapGL.Point(lng, lat);
        map.centerAndZoom(point, 15);

        const marker = new BMapGL.Marker(point);
        map.clearOverlays();
        map.addOverlay(marker);

        geocoder.getLocation(point, function (result) {
          if (result) {
            const address = result.address;
            showPopup(lng, lat, address);
            addRecord(lng, lat, address);
          }
        });
      } else {
        const local = new BMapGL.LocalSearch(map, {
          onSearchComplete: function (results) {
            if (local.getStatus() === BMAP_STATUS_SUCCESS) {
              const poi = results.getPoi(0);
              const lng = poi.point.lng.toFixed(6);
              const lat = poi.point.lat.toFixed(6);
              map.centerAndZoom(poi.point, 15);

              const marker = new BMapGL.Marker(poi.point);
              map.clearOverlays();
              map.addOverlay(marker);

              showPopup(lng, lat, poi.address);
              addRecord(lng, lat, poi.address);
            } else {
              alert("未找到地址");
            }
          },
        });
        local.search(input);
      }
    }

    function getCurrentLocation() {
      const geolocation = new BMapGL.Geolocation();
      geolocation.getCurrentPosition(
        function (r) {
          if (geolocation.getStatus() == BMAP_STATUS_SUCCESS) {
            const point = r.point;
            const lng = point.lng.toFixed(6);
            const lat = point.lat.toFixed(6);
            map.centerAndZoom(point, 15);

            const marker = new BMapGL.Marker(point);
            map.clearOverlays();
            map.addOverlay(marker);

            geocoder.getLocation(point, function (result) {
              const address = result ? result.address : "未知位置";
              showPopup(lng, lat, address);
              addRecord(lng, lat, address);
            });
          } else {
            // fallback to browser location
            console.warn("百度定位失败，尝试使用 HTML5 定位...");
            if (navigator.geolocation) {
              navigator.geolocation.getCurrentPosition(
                (pos) => {
                  const lat = pos.coords.latitude.toFixed(6);
                  const lng = pos.coords.longitude.toFixed(6);
                  const point = new BMapGL.Point(lng, lat);
                  map.centerAndZoom(point, 15);

                  const marker = new BMapGL.Marker(point);
                  map.clearOverlays();
                  map.addOverlay(marker);

                  geocoder.getLocation(point, function (result) {
                    const address = result ? result.address : "未知位置";
                    showPopup(lng, lat, address);
                    addRecord(lng, lat, address);
                  });
                },
                (error) => {
                  alert("HTML5定位失败：" + error.message);
                },
                { enableHighAccuracy: true, timeout: 10000 }
              );
            } else {
              alert("浏览器不支持定位。");
            }
          }
        },
        { enableHighAccuracy: true }
      );
    }

    // 地图点击事件，获取坐标和地址
    map.addEventListener("click", function (e) {
      const lng = e.latlng.lng.toFixed(6);
      const lat = e.latlng.lat.toFixed(6);
      const point = new BMapGL.Point(lng, lat);

      const marker = new BMapGL.Marker(point);
      map.clearOverlays();
      map.addOverlay(marker);

      geocoder.getLocation(point, function (result) {
        const address = result ? result.address : "未知位置";
        showPopup(lng, lat, address);
        addRecord(lng, lat, address);
      });
    });
  </script>
</body>
</html>
