# quick-install-with-kubeadm

centos7.3下利用kubeadm快速安装kubernets

##### k8s环境 
主机名  | 内网ip | 外网ip |
:---|:---:|:---:|
k8s-master| 172.16.0.69 |106.13.26.221|
k8s-node-1 | 172.16.0.70|-|
k8s-node-2 | 172.16.0.71|-|


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

#查看集群成员
/k8s/etcd/bin/etcdctl --ca-file=/k8s/etcd/ssl/ca.pem --cert-file=/k8s/etcd/ssl/server.pem --key-file=/k8s/etcd/ssl/server-key.pem --endpoints="https://172.16.0.69:2379,https://172.16.0.70:2379,https://172.16.0.71" member list

#查看集群健康
/k8s/etcd/bin/etcdctl --ca-file=/k8s/etcd/ssl/ca.pem --cert-file=/k8s/etcd/ssl/server.pem --key-file=/k8s/etcd/ssl/server-key.pem --endpoints="https://172.16.0.69:2379,https://172.16.0.70:2379,https://172.16.0.71" cluster-health
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

```
