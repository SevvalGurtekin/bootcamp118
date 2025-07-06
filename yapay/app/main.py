from fastapi import FastAPI, Request
from fastapi.templating import Jinja2Templates
from fastapi.responses import HTMLResponse, RedirectResponse
from app.db.session import engine
from app.db import models
from app.routers import auth, admin, teacher, student, parent
import os

app = FastAPI(
    title="AI Destekli Özel Eğitim Platformu",
    description="FastAPI ile geliştirilen, rol bazlı özel eğitim platformu",
    version="1.0.0"
)

# Veritabanı tablolarını oluştur
models.Base.metadata.create_all(bind=engine)

# Jinja2 şablonlar dizini tanımlanıyor
templates = Jinja2Templates(directory=os.path.join(os.path.dirname(__file__), "templates"))

# Router'lar projeye dahil ediliyor
app.include_router(auth.router, prefix="/auth", tags=["Authentication"])
app.include_router(admin.router, tags=["Admin Panel"])
app.include_router(teacher.router, tags=["Teacher"])
app.include_router(student.router, tags=["Student"])
app.include_router(parent.router, tags=["Parent"])

# Ana sayfaya gelen kullanıcıyı giriş sayfasına yönlendir
@app.get("/", response_class=HTMLResponse)
def root_redirect(request: Request):
    return RedirectResponse("/auth/login")
