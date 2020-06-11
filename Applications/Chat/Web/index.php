<html>
<head>
  <meta http-equiv="Content-Type"
        content="text/html; charset=utf-8">
  <title>workerman-chat PHP聊天室 Websocket(HTLM5/Flash)+PHP多进程socket实时推送技术</title>
  <link href="/css/bootstrap.min.css"
        rel="stylesheet">
  <link href="/css/jquery-sinaEmotion-2.1.0.min.css"
        rel="stylesheet">
  <link href="/css/style.css"
        rel="stylesheet">

  <script type="text/javascript"
          src="/js/swfobject.js"></script>
  <script type="text/javascript"
          src="/js/web_socket.js"></script>
  <script type="text/javascript"
          src="/js/jquery.min.js"></script>
  <script type="text/javascript"
          src="/js/jquery-sinaEmotion-2.1.0.min.js"></script>

  <script type="text/javascript">
      if (typeof console == "undefined") {
          this.console = {
              log: function (msg) {
              }
          };
      }
      // 如果浏览器不支持websocket，会使用这个flash自动模拟websocket协议，此过程对开发者透明
      WEB_SOCKET_SWF_LOCATION = "/swf/WebSocketMain.swf";
      // 开启flash的websocket debug
      WEB_SOCKET_DEBUG = true;
      var ws, name, client_list = {}, yourId = 0;

      // 连接服务端
      function connect() {
          // 创建websocket
          ws = new WebSocket("ws://" + document.domain + ":7272");
          // 当socket连接打开时，输入用户名
          ws.onopen = onopen;
          // 当有消息时根据消息类型显示不同信息
          ws.onmessage = onmessage;
          ws.onclose = function () {
              console.log("连接关闭，定时重连");
              connect();
          };
          ws.onerror = function () {
              console.log("出现错误");
          };
      }

      // 连接建立时发送登录信息
      function onopen() {
          if (!name) {
              show_prompt();
          }
          // 登录
          var login_data = '{"type":"login","client_name":"' + name.replace(/"/g, '\\"') + '","room_id":"<?php echo isset($_GET['room_id']) ? $_GET['room_id'] : 1?>"}';
          console.log("websocket握手成功，发送登录数据:" + login_data);
          ws.send(login_data);
      }

      // 服务端发来消息时
      function onmessage(e) {
          var data = JSON.parse(e.data);
          switch (data['type']) {
              // 服务端ping客户端
              case 'ping':
                  ws.send('{"type":"pong"}');
                  break;
              // 登录 更新用户列表
              case 'login':
                  //{"type":"login","client_id":xxx,"client_name":"xxx","client_list":"[...]","time":"xxx"}
                  say(data['client_id'], data['client_name'], data['client_name'] + ' 加入了聊天室', data['time']);
                  if (data['client_list']) {
                      client_list = data['client_list'];
                  } else {
                      client_list[data['client_id']] = data['client_name'];
                  }
                  flush_client_list();
                  console.log(data['client_name'] + "登录成功");
                  break;
              // 发言
              case 'say':
                  let content = data.content;
                  try {
                      content = JSON.parse(content.replace(/&quot;/g, '"'));
                  } catch (e) {
                      console.log(e);
                  }
                  if (typeof content == 'object' && content.img) {
                      content = '<img src="' + content.img + '"/>';
                  }
                  //{"type":"say","from_client_id":xxx,"to_client_id":"all/client_id","content":"xxx","time":"xxx"}
                  say(data['from_client_id'], data['from_client_name'], content, data['time']);
                  showMess({
                      name: data['from_client_name'],
                      img: 'http://lorempixel.com/38/38/?' + data['from_client_id']
                  }, data['content']);
                  autoScroll();//自动滚动
                  break;
              // 用户退出 更新用户列表
              case 'logout':
                  //{"type":"logout","client_id":xxx,"time":"xxx"}
                  say(data['from_client_id'], data['from_client_name'], data['from_client_name'] + ' 退出了', data['time']);
                  delete client_list[data['from_client_id']];
                  flush_client_list();
                  break;
              case'id':
                  yourId = data.id;
                  break;
          }
      }

      // 输入姓名
      function show_prompt() {
          name = prompt('输入你的名字：', '');
          if (!name || name == 'null') {
              name = '游客';
          }
      }

      // 提交对话
      function onSubmit() {
          var input = document.getElementById("textarea");
          var to_client_id = $("#client_list option:selected").attr("value");
          var to_client_name = $("#client_list option:selected").text();
          ws.send('{"type":"say","to_client_id":"' + to_client_id + '","to_client_name":"' + to_client_name + '","content":"' + input.value.replace(/"/g, '\\"').replace(/\n/g, '\\n').replace(/\r/g, '\\r') + '"}');
          input.value = "";
          input.focus();
      }

      /**
       * 发送图片
       * @param data_url
       */
      function sendImg(data_url) {
          var to_client_id = $("#client_list option:selected").attr("value");
          var to_client_name = $("#client_list option:selected").text();
          let message = {
              type: "say",
              to_client_id,
              to_client_name,
              content: JSON.stringify({img: data_url})
          };
          return ws.send(JSON.stringify(message));
      }

      // 刷新用户列表框
      function flush_client_list() {
          var userlist_window = $("#userlist");
          var client_list_slelect = $("#client_list");
          userlist_window.empty();
          client_list_slelect.empty();
          userlist_window.append('<h4>在线用户</h4><ul>');
          client_list_slelect.append('<option value="all" id="cli_all">所有人</option>');
          for (var p in client_list) {
              userlist_window.append('<li id="' + p + '">' + client_list[p] + '</li>');
              client_list_slelect.append('<option value="' + p + '">' + client_list[p] + '</option>');
          }
          $("#client_list").val(select_client_id);
          userlist_window.append('</ul>');
      }

      /**
       * 判断客户端id是不是自己
       * @param client_id
       * @return {boolean}
       */
      function isYou(client_id) {
          return yourId == client_id;
      }

      // 发言
      function say(from_client_id, from_client_name, content, time, isPictur = false) {
          let isYou = yourId == from_client_id;
          //解析新浪微博图片
          content = content.replace(/(http|https):\/\/[\w]+.sinaimg.cn[\S]+(jpg|png|gif)/gi, function (img) {
                  return "<a target='_blank' href='" + img + "'>" + "<img src='" + img + "'>" + "</a>";
              }
          );
          content = content.replace(/data:image[\S]+=?/gi, function (img) {
                  img = img.replace(/=/g, '');
                  return "<a target='_blank' href='" + img + "'>" + "<img src='" + img + "'>" + "</a>";
              }
          );
          //解析url
          content = content.replace(/(http|https):\/\/[\S]+/gi, function (url) {
                  if (url.indexOf(".sinaimg.cn/") < 0)
                      return "<a target='_blank' href='" + url + "'>" + url + "</a>";
                  else
                      return url;
              }
          );
          let isYourself = isYou(from_client_id);
          let sessionHtml = isYourself
              ? '<div class="speech_item" style="float: right;">'
              + '<img src="http://lorempixel.com/38/38/?' + from_client_id + '" class="user_icon" style="float: right;" /> ' +
              '<div style="float:right;text-align:right;">'
              + from_client_name + "<strong>(自己)</strong>"
              + '<br> ' + time +
              '</div>' +
              '<div style="clear:both;"></div>' +
              '<p class="triangle-isosceles top">' + content + '</p> ' +
              '</div>' +
              '<div style="clear: both;"></div>'

              : '<div class="speech_item">'
              + '<img src="http://lorempixel.com/38/38/?' + from_client_id + '" class="user_icon" /> '
              + from_client_name
              + '<br> ' + time +
              '<div style="clear:both;"></div>' +
              '<p class="triangle-isosceles top">' + content + '</p> ' +
              '</div>';

          $("#dialog").append(sessionHtml).parseEmotion();
      }

      $(function () {
          select_client_id = 'all';
          $("#client_list").change(function () {
              select_client_id = $("#client_list option:selected").attr("value");
          });
          $('.face').click(function (event) {
              $(this).sinaEmotion();
              event.stopPropagation();
          });
      });

      //注册桌面通知
      suportNotify();

      //判断浏览器是否支持Web Notifications API
      function suportNotify() {
          if (window.Notification) {
              // 支持
              console.log("支持" + "Web Notifications API");
              //如果支持Web Notifications API，再判断浏览器是否支持弹出实例
          } else {
              // 不支持
              alert("不支持 Web Notifications API");
          }
      }

      let notCheck = true;

      //判断浏览器是否支持弹出实例
      function showMess(user = {}, content = '') {
          console.log('1：' + Notification.permission);
          //如果支持window.Notification 并且 许可不是拒绝状态
          if (window.Notification && notCheck) {
              //Notification.requestPermission这是一个静态方法，作用就是让浏览器出现是否允许通知的提示
              Notification.requestPermission(function (status) {
                  console.log('2: ' + status);
                  //如果状态是同意
                  if (status === "granted") {
                      var m = new Notification(user.name || '收到信息', {
                          body: content || '这里是通知内容！你想看什么客官？',　　//消息体内容
                          icon: user.img || "images/img1.jpg"　　//消息图片
                      });
                      m.onclick = function () {//点击当前消息提示框后，跳转到当前页面
                          window.focus();
                          m.close();
                      }
                      setTimeout(() => m.close(), 4e3);
                  } else {
                      alert('当前浏览器不支持弹出消息');
                      notCheck = false;
                  }
              });
          }
      }
  </script>
</head>
<body onload="connect();">
<div class="container">
  <div class="row clearfix">
    <div class="col-md-1 column">
    </div>
    <div class="col-md-6 column">
      <div class="thumbnail">
        <div class="caption"
             id="dialog"></div>
      </div>
      <form onsubmit="onSubmit(); return false;">
        <select style="margin-bottom:8px"
                id="client_list">
          <option value="all">所有人</option>
        </select>
        <textarea class="textarea thumbnail"
                  id="textarea"></textarea>
        <div class="say-btn">
          <input type="button"
                 class="btn btn-default face pull-left"
                 value="表情"/>
          <input type="submit"
                 class="btn btn-default"
                 value="发表"/>
        </div>
      </form>
      <div>
        &nbsp;&nbsp;&nbsp;&nbsp;<b>房间列表:</b>（当前在&nbsp;房间<?php echo isset($_GET['room_id']) && intval($_GET['room_id']) > 0 ? intval($_GET['room_id']) : 1; ?>
        ）<br>
        &nbsp;&nbsp;&nbsp;&nbsp;<a href="/?room_id=1">房间1</a>&nbsp;&nbsp;&nbsp;&nbsp;<a href="/?room_id=2">房间2</a>&nbsp;&nbsp;&nbsp;&nbsp;<a href="/?room_id=3">房间3</a>&nbsp;&nbsp;&nbsp;&nbsp;<a href="/?room_id=4">房间4</a>
        <br><br>
      </div>
      <p class="cp">PHP多进程+Websocket(HTML5/Flash)+PHP Socket实时推送技术&nbsp;&nbsp;&nbsp;&nbsp;Powered by
        <a href="http://www.workerman.net/workerman-chat"
           target="_blank">workerman-chat</a></p>
    </div>
    <div class="col-md-3 column">
      <div class="thumbnail">
        <div class="caption"
             id="userlist"></div>
      </div>
    </div>
  </div>
</div>
<script type="text/javascript">var _bdhmProtocol = (("https:" == document.location.protocol) ? " https://" : " http://");
    document.write(unescape("%3Cscript src='" + _bdhmProtocol + "hm.baidu.com/h.js%3F7b1919221e89d2aa5711e4deb935debd' type='text/javascript'%3E%3C/script%3E"));</script>
<script type="text/javascript">
    // 动态自适应屏幕
    document.write('<meta name="viewport" content="width=device-width,initial-scale=1">');
    $("textarea").on("keydown", function (e) {
        // 按enter键自动提交
        if (e.keyCode === 13 && !e.ctrlKey) {
            e.preventDefault();
            $('form').submit();
            return false;
        }

        // 按ctrl+enter组合键换行
        if (e.keyCode === 13 && e.ctrlKey) {
            $(this).val(function (i, val) {
                return val + "\n";
            });
        }
    });

    function autoScroll() {
        var ele = document.getElementById('dialog');
        ele.scrollTop = ele.scrollHeight;
    }

    $("#textarea").on('paste', function (event) {
        let isChrome = false;
        if (event.clipboardData || event.originalEvent) {
            //某些chrome版本使用的是event.originalEvent
            let clipboardData = (event.clipboardData || event.originalEvent.clipboardData);
            if (clipboardData.items) {
                // for chrome
                var items = clipboardData.items,
                    len = items.length,
                    blob = null;
                isChrome = true;
                for (let i = 0; i < len; i++) {
                    if (items[i].type.indexOf("image") !== -1) {
                        //getAsFile() 此方法只是living standard firefox ie11 并不支持
                        blob = items[i].getAsFile();
                        let reader = new FileReader();
                        reader.readAsDataURL(blob);
                        let that = this;
                        reader.onload = function (e) {
                            $(that).val($(that).val() + this.result + "=");
                            onSubmit();
                            // sendImg(this.result);
                        };
                    }
                }
            }
        }
    });
</script>
</body>
</html>
