# 用redis-trib 进行初始化

kubectl run -it ubuntu --image=ubuntu --restart=Never  bash

### 更新源
```
cat > /etc/apt/sources.list << EOF
deb http://mirrors.tuna.tsinghua.edu.cn/ubuntu/ xenial main restricted
deb http://mirrors.tuna.tsinghua.edu.cn/ubuntu/ xenial-updates main restricted
deb http://mirrors.tuna.tsinghua.edu.cn/ubuntu/ xenial universe
deb http://mirrors.tuna.tsinghua.edu.cn/ubuntu/ xenial-updates universe
deb http://mirrors.tuna.tsinghua.edu.cn/ubuntu/ xenial multiverse
deb http://mirrors.tuna.tsinghua.edu.cn/ubuntu/ xenial-updates multiverse
deb http://mirrors.tuna.tsinghua.edu.cn/ubuntu/ xenial-backports main restricted universe multiverse
deb http://mirrors.tuna.tsinghua.edu.cn/ubuntu/ xenial-security main restricted
deb http://mirrors.tuna.tsinghua.edu.cn/ubuntu/ xenial-security universe
deb http://mirrors.tuna.tsinghua.edu.cn/ubuntu/ xenial-security multiverse
EOF
```

### 安装redis-trib
```
apt-get update

apt-get install -y libncursesw5 libreadline6 libtinfo5 --allow-remove-essential

apt-get install -y libpython2.7-stdlib python2.7 python-pip redis-tools dnsutils


pip install --upgrade "pip < 21.0" //高版本不维护了，必须低于21

pip install redis-trib==0.5.1
```

### 初始化集群：  pod 在dns内的名称：$(podname).$(service name).$(namespace).svc.cluster.local

```
redis-trib.py create \
  `dig +short redis-app-0.redis-service.default.svc.cluster.local`:6379 \
  `dig +short redis-app-1.redis-service.default.svc.cluster.local`:6379 \
  `dig +short redis-app-2.redis-service.default.svc.cluster.local`:6379
  


 redis-trib.py replicate \
  --master-addr `dig +short redis-app-0.redis-service.default.svc.cluster.local`:6379 \
  --slave-addr `dig +short redis-app-3.redis-service.default.svc.cluster.local`:6379

 redis-trib.py replicate \
  --master-addr `dig +short redis-app-1.redis-service.default.svc.cluster.local`:6379 \
  --slave-addr `dig +short redis-app-4.redis-service.default.svc.cluster.local`:6379

 redis-trib.py replicate \
  --master-addr `dig +short redis-app-2.redis-service.default.svc.cluster.local`:6379 \
  --slave-addr `dig +short redis-app-5.redis-service.default.svc.cluster.local`:6379
 ```
### 检查集群正确性
```
kubectl exec -it redis-app-2 /bin/bash
/data# /usr/local/bin/redis-cli -c
127.0.0.1:6379> cluster nodes
127.0.0.1:6379> cluster info
```

### 测试主从切换
```
 kubectl get pods redis-app-0 -o wide

kubectl exec -it redis-app-0 /bin/bash
root@redis-app-0:/data# /usr/local/bin/redis-cli -c
127.0.0.1:6379> role
1) "master"
2) (integer) 13370
3) 1) 1) "172.17.63.9"
      2) "6379"
      3) "13370"
127.0.0.1:6379> 

#手动删除 redis-app-0 
kubectl delete pod redis-app-0

kubectl get pod redis-app-0 -o wide
kubectl exec -it redis-app-0 /bin/bash
root@redis-app-0:/data# /usr/local/bin/redis-cli -c
127.0.0.1:6379> role
1) "slave"
2) "172.17.63.9"
3) (integer) 6379
4) "connected"
5) (integer) 13958
```
