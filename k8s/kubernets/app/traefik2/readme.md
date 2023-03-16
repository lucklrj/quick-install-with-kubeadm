```
traefix2 支持tcp/upp转发

# 用helm 安装traefik2
helm upgrade --install traefik ./traefik -f ./v2.yaml --namespace kube-system


# 生成密码
htpasswd -bc basic-auth-secret admin admin

# 设置k8s secret
kubectl create secret generic traefik2-dashbroad-basic-auth --from-file=basic-auth-secret -n kube-system

# 安装dashbroad
kubectl create -f traefik-dashboard.yaml
```

