#### 系统层面注意事项
1、php8.1 + mysql8.0 + nginx
2、php一定要安装extension=event.so扩展 修改上传文件 max_execution_time=100 post_max_size=100m upload_max_filesize=120m memory_limit=256
3、cat /proc/sys/fs/file-max 看下这个值是不是很大
4、打开文件 /etc/sysctl.conf，增加以下设置
    #该参数设置系统的TIME_WAIT的数量，如果超过默认值则会被立即清除
    net.ipv4.tcp_max_tw_buckets = 20000
    #定义了系统中每一个端口最大的监听队列的长度，这是个全局的参数
    net.core.somaxconn = 65535
    #对于还未获得对方确认的连接请求，可保存在队列中的最大数目
    net.ipv4.tcp_max_syn_backlog = 262144
    #在每个网络接口接收数据包的速率比内核处理这些包的速率快时，允许送到队列的数据包的最大数目
    net.core.netdev_max_backlog = 30000
    #此选项会导致处于NAT网络的客户端超时，建议为0。Linux从4.12内核开始移除了 tcp_tw_recycle 配置，如果报错"No such file or directory"请忽略
    net.ipv4.tcp_tw_recycle = 0
    #系统所有进程一共可以打开的文件数量
    fs.file-max = 6815744
    #防火墙跟踪表的大小。注意：如果防火墙没开则会提示error: "net.netfilter.nf_conntrack_max" is an unknown key，忽略即可
    net.netfilter.nf_conntrack_max = 2621440
    运行 sysctl -p 即刻生效。
5、ulimit -HSn 102400 && source /etc/profile && 需要重启服务进程
6、nginx 需要配置
    client_max_body_size 100m;
    location /wss
    {
        proxy_read_timeout 60s;
        proxy_send_timeout 60s;
        proxy_pass http://127.0.0.1:8282;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "Upgrade";
        proxy_set_header X-Real-IP $remote_addr;
    }
7、worker进程设置成cpu的8倍
8、linux需要安装该软件 apt-get install ffmpeg -y
