# quick-install-with-kubeadm

centos7.3下利用kubeadm快速安装kubernets

##### k8s环境 
主机名  | 内网ip | 外网ip |
:---|:---:|:---:|
k8s-master| 172.16.0.69 |106.13.26.221|
k8s-node-1 | 172.16.0.70|-|
k8s-node-2 | 172.16.0.71|-|


##### 百度k8s环境 
https://cloud.baidu.com 15882002095
主机名  | 内网ip | 外网ip |
:---|:---:|:---:|
k8s-master| 172.16.0.69 |106.13.26.221|
k8s-node-1 | 172.16.0.70|-|
k8s-node-2 | 172.16.0.71|-|

*etcd面板账号*  
etcd-admin  
Fx86F821Vmsr

*jenkis账号*
admin  
os1Kh8e1jgwWXCRh

*百度 镜像仓库密码*  
[访问地址](https://console.bce.baidu.com/cce/?_=1561777471301#/cce/image/list)  
账号：hicoffice  
密码：GA%#xh@@bD@DwOJ4j

--------

1. 更新语言
```
/etc/environment
LC_ALL=en_US.utf8
LC_CTYPE=en_US.utf8
LANG=en_US.utf8
```

2. 需改hosts节点
```
vi /etc/hosts
172.16.0.69 k8s-master
172.16.0.70 k8s-node-1
172.16.0.71 k8s-node-2
```
3. 关闭防火墙
```
systemctl stop firewalld
systemctl disable firewalld
```

4. 禁用Selinux
```
setenforce 0
sed -i --follow-symlinks 's/SELINUX=enforcing/SELINUX=disabled/g' /etc/sysconfig/selinux
```

5. 设置内核参数
```
cat <<EOF > /etc/sysctl.d/k8s.conf
net.bridge.bridge-nf-call-ip6tables = 1
net.bridge.bridge-nf-call-iptables = 1
net.ipv4.ip_forward = 1
EOF

sysctl --system && modprobe br_netfilter
```

6. 开启ipvs(加载IPVS模块)
```
cat > /etc/sysconfig/modules/ipvs.modules <<EOF
#!/bin/bash
modprobe -- ip_vs
modprobe -- ip_vs_rr
modprobe -- ip_vs_wrr
modprobe -- ip_vs_sh
modprobe -- nf_conntrack_ipv4
EOF


chmod 755 /etc/sysconfig/modules/ipvs.modules && bash /etc/sysconfig/modules/ipvs.modules && lsmod | grep -e ip_vs -e nf_conntrack_ipv4
```

7. 安装docker
```
#设置国内yum源
wget https://mirrors.aliyun.com/docker-ce/linux/centos/docker-ce.repo -O /etc/yum.repos.d/docker-ce.repo

#安装docker 
yum install -y yum-utils device-mapper-persistent-data lvm2

yum -y install docker-ce-18.09.6-3.el7

#启动docker服务
systemctl enable docker && systemctl start docker
docker --version

#修改各个节点上docker的将cgroup driver改为systemd
mkdir /etc/docker

# Setup daemon.
cat > /etc/docker/daemon.json <<EOF
{
  "exec-opts": ["native.cgroupdriver=systemd"],
  "log-driver": "json-file",
  "log-opts": {
    "max-size": "100m"
  },
  "storage-driver": "overlay2",
  "storage-opts": [
    "overlay2.override_kernel_check=true"
  ]
}
EOF

mkdir -p /etc/systemd/system/docker.service.d

# Restart Docker
systemctl daemon-reload
systemctl restart docker
```

8.安装etcd
```
#cfssl安装
wget https://pkg.cfssl.org/R1.2/cfssl_linux-amd64
wget https://pkg.cfssl.org/R1.2/cfssljson_linux-amd64
wget https://pkg.cfssl.org/R1.2/cfssl-certinfo_linux-amd64
chmod +x cfssl_linux-amd64 cfssljson_linux-amd64 cfssl-certinfo_linux-amd64
mv cfssl_linux-amd64 /usr/local/bin/cfssl
mv cfssljson_linux-amd64 /usr/local/bin/cfssljson
mv cfssl-certinfo_linux-amd64 /usr/bin/cfssl-certinfo

#生成etcd ca证书和私钥 初始化ca
cfssl gencert -initca ca-csr.json | cfssljson -bare ca 

#生成server证书
cfssl gencert -ca=ca.pem -ca-key=ca-key.pem -config=ca-config.json -profile=etcd server-csr.json | cfssljson -bare server

#配置etcd启动文件
cp /k8s/etcd/cfg/backup/etcd.service /usr/lib/systemd/system/

#配置etcd文件
cp /k8s/etcd/cfg/backup/etcd.conf.0N /k8s/etcd/cfg/etcd.conf

#启动etcd
systemctl daemon-reload
systemctl enable etcd
systemctl start etcd

#查看服务装填
systemctl status etcd

#切换etcd版本
export ETCDCTL_API=2,[3]

#v2操作命令

#查看集群成员
/k8s/etcd/bin/etcdctl \
--ca-file=/k8s/etcd/ssl/ca.pem \
--cert-file=/k8s/etcd/ssl/server.pem \
--key-file=/k8s/etcd/ssl/server-key.pem \
--endpoints="https://172.16.0.69:2379,https://172.16.0.70:2379,https://172.16.0.71" \
member list

#查看集群健康
/k8s/etcd/bin/etcdctl \
--ca-file=/k8s/etcd/ssl/ca.pem \
--cert-file=/k8s/etcd/ssl/server.pem \
--key-file=/k8s/etcd/ssl/server-key.pem \
--endpoints="https://172.16.0.69:2379,https://172.16.0.70:2379,https://172.16.0.71" \
cluster-health

#v3操作命令
/k8s/etcd/bin/etcdctl \
--cacert=/k8s/etcd/ssl/ca.pem \
--cert=/k8s/etcd/ssl/server.pem \
--key=/k8s/etcd/ssl/server-key.pem \
--endpoints="https://172.16.0.69:2379,https://172.16.0.70:2379,https://172.16.0.71:2379" \
member list
```
*一般故障处理步骤*：
- etcdctl member remove ID
- etcdctl member add ID url
- 启动故障etcd

9. 安装k8s  
```
#新增yum源
cat <<EOF > /etc/yum.repos.d/kubernetes.repo
[kubernetes]
name=Kubernetes
baseurl=https://mirrors.aliyun.com/kubernetes/yum/repos/kubernetes-el7-x86_64/
enabled=1
gpgcheck=1
repo_gpgcheck=1
gpgkey=https://mirrors.aliyun.com/kubernetes/yum/doc/yum-key.gpg 
    https://mirrors.aliyun.com/kubernetes/yum/doc/rpm-package-key.gpg
EOF

yum makecache fast
yum list |grep kube

yum install -y kubelet-1.14.3 kubeadm-1.14.3 kubectl-1.14.3 ipvsadm

#产生初始化配置文件
kubeadm config print init-default > kubeadm.conf 
kubeadm init --config kubeadm.conf

#更新网络插件
kubectl apply -f http://mirror.faasx.com/k8s/canal/v3.3/rbac.yaml
kubectl apply -f http://mirror.faasx.com/k8s/canal/v3.3/canal.yaml
```
10. 常用命令
```
kubectl get cs
kubectl get nodes --all-namespaces
kubectl get services --all-namespaces
kubeadm reset #卸载集群时，master，node都要执行
```

11. docker私有库继承到k8s
```
kubectl create secret docker-registry lrj-alyun --docker-server=registry.cn-beijing.aliyuncs.com --docker-username=***** --docker-password=****** --docker-email=sunny_lrj@yeah.net 
```

12. etcd管理面板 
```
#https://github.com/evildecay/etcdkeeper
./etcdkeeper -p 8111  -usetls -cacert=/k8s/etcd/ssl/ca.pem  -cert=/k8s/etcd/ssl/server.pem -key=/k8s/etcd/ssl/server-key.pem -sep app -auth

#安装htpasswd命令
yum -y install httpd-tools
htpasswd -c ./auth myusername
kubectl create secret generic mysecret --from-file auth --namespace=monitoring 

参看etcd-ui.yaml
```

13. NFS挂载
```
#安装
yum install -y nfs-utils rpcbind

vim /etc/exports
追加内容：/k8s/kubernets/data *(rw,sync,no_root_squash)


exportfs -r

systemctl enable rpcbind
systemctl start rpcbind

systemctl enable nfs
systemctl start nfs

#查看共享点
showmount -e 172.16.0.69

#挂载磁盘
mount -t nfs -o nolock k8s-master:/k8s/kubernets/data /k8s_data
```

14. confd产生配置文件
```
 confd -onetime -backend etcdv3 -node https://172.16.0.69:2379 \
 -client-ca-keys /k8s/etcd/ssl/ca.pem \
 -client-cert /k8s/etcd/ssl/server.pem \
 -client-key /k8s/etcd/ssl/server-key.pem 
```


15. traefik支持https
```
kubectl create secret generic hicoffice-cert \
--from-file=/k8s/kubernets/data/http-ssl/hi-coffice/STAR.hi-coffice.com.crt \
--from-file=/k8s/kubernets/data/http-ssl/hi-coffice/STAR.hi-coffice.com.key \
-n kube-system

kubectl create configmap traefik-conf --from-file=traefik.toml -n kube-system
      
```


```
